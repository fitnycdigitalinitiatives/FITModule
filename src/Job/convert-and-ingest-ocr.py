#!/usr/bin/env python3

from __future__ import annotations

import argparse
import pysolr
import json
import requests
from requests.auth import HTTPBasicAuth
import boto3
import botocore.exceptions
from urllib.parse import urlparse

import argparse
import enum
import html
import io
import re
import select
import sys
from dataclasses import dataclass
from html.entities import html5 as html_entities
from pathlib import Path
from typing import Iterable
from xml.etree import ElementTree as ET


HOCR_PROP_PATS = [
    re.compile("(?P<key>bbox) (?P<value>\\d+ \\d+ \\d+ \\d+)"),
    re.compile("(?P<key>ppageno) (?P<value>\\d+)"),
    re.compile('(?P<key>x_source) "?(?P<value>[^;]+)"?'),
    re.compile('(?P<key>image) "(?P<value>\\d+)"'),
]
ENTITIES_PAT = re.compile(rf"&({'|'.join(k for k in html_entities.keys())});")


class EventKind(enum.Enum):
    START = 1
    END = 2
    TEXT = 3


class BoxType(enum.Enum):
    PAGE = 1
    BLOCK = 2
    LINE = 3
    WORD = 4

    @classmethod
    def from_hocr_class(cls, val: str | None) -> BoxType | None:
        if val == "ocr_page":
            return BoxType.PAGE
        elif (val == "ocr_carea") | (val == "ocr_par") | (val == "ocrx_block"):
            return BoxType.BLOCK
        elif val == "ocr_line":
            return BoxType.LINE
        elif val == "ocrx_word":
            return BoxType.WORD

    @classmethod
    def from_alto_tag(cls, val: str) -> BoxType | None:
        if val == "Page":
            return BoxType.PAGE
        elif val == "PrintSpace":
            return BoxType.BLOCK
        elif val == "TextBlock":
            return BoxType.BLOCK
        elif val == "TextLine":
            return BoxType.LINE
        elif val == "String":
            return BoxType.WORD

    @classmethod
    def to_miniocr_tag(cls, val: BoxType) -> str:
        if val == BoxType.PAGE:
            return "p"
        elif val == BoxType.BLOCK:
            return "b"
        elif val == BoxType.LINE:
            return "l"
        elif val == BoxType.WORD:
            return "w"


@dataclass
class ParseEvent:
    kind: EventKind
    box_type: BoxType | None
    page_id: str | None = None
    x: int | float | None = None
    y: int | float | None = None
    width: int | float | None = None
    height: int | float | None = None
    text: str | None = None


def convert_entity(entity: str) -> str:
    """ Since Python's stdlib parser can't handle named entities, we have to convert them to numeric entities. """
    # Special case: ASCII characters that need to be escaped for well-formed markup
    if entity == "lt":
        return "&#60;"
    elif entity == "gt":
        return "&#62;"
    elif entity == "amp":
        return "&#38;"
    elif entity == "quot":
        return "&#34;"
    elif entity == "apos":
        return "&#39;"
    else:
        return html_entities[entity].encode("ascii", "xmlcharrefreplace").decode("utf8")


def parse_hocr(hocr: bytes) -> Iterable[ParseEvent]:
    # hOCR can used named entities, which Python's stdlib parser can't handle, so we
    # have to convert them to numeric entities
    fixed_hocr = ENTITIES_PAT.sub(
        lambda match: convert_entity(match.group(1)),
        hocr.decode("utf-8"),
    )

    # Track the currently parsed word across parse iterations to be able to
    # add alternatives to the word text
    cur_word: ParseEvent | None = None
    # Track if we're currently parsing alternatives for a word
    in_word_alternatives = False

    for evt in ET.iterparse(io.StringIO(fixed_hocr), events=("start", "end")):
        event, elem = evt
        kind = EventKind.START if event == "start" else EventKind.END
        box_type = BoxType.from_hocr_class(elem.attrib.get("class"))
        # Strip namespace from tag
        tag = elem.tag.split("}")[-1]

        if (
            cur_word is not None
            and tag == "span"
            and elem.attrib.get("class") == "alternatives"
        ):
            in_word_alternatives = True

        if kind == EventKind.START and cur_word is not None and in_word_alternatives:
            alternatives: list[str] = []
            if cur_word.text:
                alternatives.append(cur_word.text)
            if tag == "ins" and elem.text:
                alternatives.insert(0, elem.text)
            elif tag == "del" and elem.text:
                alternatives.append(elem.text)
            cur_word.text = "⇿".join(alternatives)

        if box_type is None:
            continue

        evt = ParseEvent(kind=kind, box_type=box_type)

        if evt.kind == EventKind.END:
            if evt.box_type == BoxType.WORD and cur_word is not None:
                # Emit the word event if the word has ended and we have picked up all
                # potential alternative readings within it
                yield cur_word
                cur_word = None
                in_word_alternatives = False
            yield evt
            if evt.box_type == BoxType.WORD and elem.tail:
                # hOCR has support for coordinate-less text nodes between words, emit these
                # as text events
                yield ParseEvent(kind=EventKind.TEXT, box_type=None, text=elem.tail)
            elem.clear()
            continue

        props = {
            match.group("key"): match.group("value")
            for match in (
                pat.search(elem.attrib.get("title")) for pat in HOCR_PROP_PATS
            )
            if match is not None
        }
        evt.page_id = next(
            (props[x]
             for x in ("x_source", "ppageno", "image") if x in props), None
        )
        if "bbox" in props:
            ulx, uly, lrx, lry = map(int, props["bbox"].split())
            evt.x, evt.y = ulx, uly
            evt.width, evt.height = lrx - ulx, lry - uly

        if evt.box_type == BoxType.WORD:
            # Don't emit word events immediately, since we might have alternatives
            evt.text = elem.text
            cur_word = evt
            continue

        yield evt


def parse_alto(alto: bytes) -> Iterable[ParseEvent]:
    # ALTO documents can have coordinates expressed as 1/1200th of an inch or as millimeters,
    # in these cases we can only use relative coordinates in the MiniOCR output format, since
    # we don't know the DPI of the original document.
    use_relative = False
    relative_reference: tuple[int, int] | None = None

    # We track the currently parsed word across parse iterations to be able to
    # add alternatives to the word text
    cur_word: ParseEvent | None = None
    for evt in ET.iterparse(io.BytesIO(alto), events=("start", "end")):
        event, elem = evt
        kind = EventKind.START if event == "start" else EventKind.END
        # Strip namespace from tag
        tag = elem.tag.split("}")[-1]

        if kind == EventKind.START:
            if tag == "SP":
                yield ParseEvent(kind=EventKind.TEXT, box_type=None, text=" ")

            if tag == "MeasurementUnit" and elem.text != "pixel":
                use_relative = True

            if tag == "ALTERNATIVE" and cur_word is not None:
                cur_word.text = f"{cur_word.text}⇿{elem.text}"

        box_type = BoxType.from_alto_tag(tag)
        if box_type is None:
            continue

        evt = ParseEvent(kind=kind, box_type=box_type)

        if evt.kind == EventKind.END:
            # We only emit the word event if the word has ended, since there might
            # have been alternatives within the word element in the ALTO
            if evt.box_type == BoxType.WORD and cur_word is not None:
                yield cur_word
                cur_word = None
            yield evt
            elem.clear()
            continue

        if tag == "Page":
            if "ID" in elem.attrib:
                evt.page_id = elem.attrib["ID"]
            if use_relative:
                relative_reference = (
                    int(elem.attrib["WIDTH"]),
                    int(elem.attrib["HEIGHT"]),
                )

        if all(attr in elem.attrib for attr in ["HPOS", "VPOS", "WIDTH", "HEIGHT"]):
            evt.x = float(elem.attrib["HPOS"])
            evt.y = float(elem.attrib["VPOS"])
            evt.width = float(elem.attrib["WIDTH"])
            evt.height = float(elem.attrib["HEIGHT"])
            if use_relative and relative_reference:
                # Non-pixel coordinates are emitted as relative coordinates
                evt.x = evt.x / relative_reference[0]
                evt.y = evt.y / relative_reference[1]
                evt.width = evt.width / relative_reference[0]
                evt.height = evt.height / relative_reference[1]
            else:
                evt.x = int(evt.x)
                evt.y = int(evt.y)
                evt.width = int(evt.width)
                evt.height = int(evt.height)

        if evt.box_type == BoxType.WORD:
            evt.text = elem.attrib.get("CONTENT")

            # Handle hyphenation
            subs_type = elem.attrib.get("SUBS_TYPE")

            if subs_type == "HypPart1":
                evt.text = f"{evt.text}\xad"

            cur_word = evt
            continue
        yield evt


def generate_miniocr(evts: Iterable[ParseEvent]) -> Iterable[str]:
    yield "<ocr>"

    # Used to determine if there should be inter-line whitespace
    last_txt_was_hyphen = False

    for evt in evts:
        if evt.kind == EventKind.TEXT and evt.text:
            # Ignore whitespace-only text if the last text ended on a hyphen
            if last_txt_was_hyphen and not evt.text.strip():
                continue
            yield evt.text
            last_txt_was_hyphen = evt.text.endswith("\xad")
            continue

        if evt.box_type is None:
            continue

        if evt.kind == EventKind.START:
            tag = BoxType.to_miniocr_tag(evt.box_type)
            attribs = []
            if evt.box_type == BoxType.PAGE:
                if evt.page_id:
                    attribs.append(f'xml:id="{evt.page_id}"')
                if (
                    evt.width
                    and evt.height
                    and all(isinstance(x, int) for x in (evt.width, evt.height))
                ):
                    # Only add page dimensions if we have integers, i.e. pixel dimensions
                    attribs.append(f'wh="{evt.width} {evt.height}"')
            elif evt.box_type == BoxType.WORD:
                if all(x is not None for x in (evt.x, evt.y, evt.width, evt.height)):
                    if all(
                        isinstance(x, float)
                        for x in (evt.x, evt.y, evt.width, evt.height)
                    ):
                        # Relative coordinates are always floats, encoded without the leading zero
                        # and with four decimal places
                        attribs.append(
                            "x="
                            + " ".join(
                                f"{x:.4f}"[1:]
                                for x in (evt.x, evt.y, evt.width, evt.height)
                            )
                        )
                    else:
                        attribs.append(
                            f'x="{evt.x} {evt.y} {evt.width} {evt.height}"')
            yield f'<{tag} {" ".join(attribs)}>' if len(attribs) > 0 else f"<{tag}>"
            if evt.box_type == BoxType.WORD:
                if evt.text is not None:
                    last_txt_was_hyphen = evt.text.endswith("\xad")
                    yield html.escape(evt.text)
        elif evt.kind == EventKind.END:
            yield f"</{BoxType.to_miniocr_tag(evt.box_type)}>"
            if evt.box_type == BoxType.LINE and not last_txt_was_hyphen:
                # Add inter-line whitespace if the line did not end on a hyphenation
                yield " "
    yield "</ocr>"


def add_to_solr(mini_ocr_output, media_id, item_id, collection_ids, solr_host, solr_port, solr_path, solr_user, solr_password):
    solr = pysolr.Solr("http://" + solr_host + ":" + solr_port + "/" + solr_path + "/",
                       auth=HTTPBasicAuth(solr_user, solr_password))
    solr.add(
        {
            "media_id": media_id,
            "item_id": item_id,
            "item_set_ids": collection_ids,
            "ocr_text": mini_ocr_output,
        }
    )
    # solr.commit()
    print('indexed: ' + media_id)


def convert_ocr(xml_file):
    header_block = xml_file[:512]
    if b'<alto' in header_block:
        parse_iter = parse_alto(xml_file)
    else:
        parse_iter = parse_hocr(xml_file)
    mini_ocr_output = ""
    for chunk in generate_miniocr(parse_iter):
        mini_ocr_output += chunk
    return mini_ocr_output


def read_xml_from_s3(ocr_file, aws_key, aws_secret):
    bucket = "dip-access-bucket"
    s3_client = boto3.client(
        "s3", aws_access_key_id=aws_key, aws_secret_access_key=aws_secret)
    key = urlparse(ocr_file).path[1:]
    contents = ""
    data = s3_client.get_object(Bucket=bucket, Key=key)
    contents = data['Body'].read()
    print('Downloaded: ' + ocr_file)
    return contents


def main(media_id, item_id, collection_ids, ocr_list, solr_host, solr_port, solr_path, solr_user, solr_password, aws_key, aws_secret):
    mini_ocr_output = ""
    for ocr_file in ocr_list:
        xml_file = read_xml_from_s3(ocr_file, aws_key, aws_secret)
        mini_ocr = convert_ocr(xml_file)
        mini_ocr_output += mini_ocr
        print('converted: ' + ocr_file)

    add_to_solr(mini_ocr_output, media_id, item_id,
                collection_ids, solr_host, solr_port, solr_path, solr_user, solr_password)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Get OCR from Omeka-s Compound Object media, to miniOCR, and ingest into solr.")
    parser.add_argument(
        "-m",
        dest="media_id",
        default=None,
        help="Media id for Omeka-s Compound Object",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-i",
        dest="item_id",
        default=None,
        help="Item id for media object",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-c",
        dest="collection_ids",
        default=None,
        help="Item id for media object",
        nargs='*',
    )
    parser.add_argument(
        "--ocr_list",
        dest="ocr_list",
        default=None,
        help="List of OCR file urls",
        required=True,
        nargs='*',
    )
    parser.add_argument(
        "-sh",
        dest="solr_host",
        default=None,
        help="Solr host",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-sr",
        dest="solr_port",
        default=None,
        help="Solr port",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-sc",
        dest="solr_path",
        default=None,
        help="Solr path to core.",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-su",
        dest="solr_user",
        default=None,
        help="Solr User name",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-sp",
        dest="solr_password",
        default=None,
        help="Solr password",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-ak",
        dest="aws_key",
        default=None,
        help="AWS key",
        required=True,
        nargs='?',
    )
    parser.add_argument(
        "-as",
        dest="aws_secret",
        default=None,
        help="AWS secret",
        required=True,
        nargs='?',
    )
    args = parser.parse_args()

    main(args.media_id, args.item_id, args.collection_ids,
         args.ocr_list, args.solr_host, args.solr_port, args.solr_path, args.solr_user, args.solr_password, args.aws_key, args.aws_secret)

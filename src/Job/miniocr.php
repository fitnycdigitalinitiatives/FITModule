#!/usr/bin/env php
<?php
// Simple script to convert hOCR or ALTO to miniOCR format.
// Usage:
//     php miniocr.php input [-o output]

// Handle CLI arguments
// --- Parse CLI arguments ---
$options = getopt("o:", ["output:"]);
$outputPath = $options["o"] ?? $options["output"] ?? null;

// Get input file from remaining args (after options)
$args = array_values(array_diff($argv, array_merge([__FILE__], array_map(function ($opt) {
    return '-' . $opt;
}, array_keys($options)), $options)));

$inputPath = $args[1] ?? null;  // First positional arg after script name

// --- Read input ---
if ($inputPath === null || $inputPath === "-") {
    // Read from STDIN
    if (posix_isatty(STDIN)) {
        fwrite(STDERR, "Waiting for input on stdin...\n");
    }
    $input = stream_get_contents(STDIN);
} else {
    $input = file_get_contents($inputPath);
    if ($input === false) {
        fwrite(STDERR, "Failed to read input file: $inputPath\n");
        exit(1);
    }
}

// --- Detect format ---
$head = substr($input, 0, 512);
$isAlto = stripos($head, '<alto') !== false;

// --- Parse events ---
$events = $isAlto ? parse_alto($input) : parse_hocr($input);

// --- Generate output ---
$output = implode('', generate_miniocr($events));

// --- Write output ---
if ($outputPath) {
    file_put_contents($outputPath, $output);
} else {
    echo $output;
}

// Enums using class constants
class EventKind
{
    const START = 1;
    const END = 2;
    const TEXT = 3;
}

class BoxType
{
    const PAGE = 1;
    const BLOCK = 2;
    const LINE = 3;
    const WORD = 4;

    public static function fromHocrClass($val)
    {
        return match ($val) {
            "ocr_page" => self::PAGE,
            "ocr_carea", "ocr_par", "ocrx_block" => self::BLOCK,
            "ocr_line" => self::LINE,
            "ocrx_word" => self::WORD,
            default => null
        };
    }

    public static function fromAltoTag($val)
    {
        return match ($val) {
            "Page" => self::PAGE,
            "PrintSpace", "TextBlock" => self::BLOCK,
            "TextLine" => self::LINE,
            "String" => self::WORD,
            default => null
        };
    }

    public static function toMiniocrTag($val)
    {
        return match ($val) {
            self::PAGE => "p",
            self::BLOCK => "b",
            self::LINE => "l",
            self::WORD => "w"
        };
    }
}

// Simple equivalent of Python's dataclass
class ParseEvent
{
    public $kind;
    public $boxType;
    public $pageId = null;
    public $x = null;
    public $y = null;
    public $width = null;
    public $height = null;
    public $text = null;

    public function __construct($kind, $boxType)
    {
        $this->kind = $kind;
        $this->boxType = $boxType;
    }
}

function convert_entity(string $entity): string
{
    $named = [
        "lt" => "&#60;",
        "gt" => "&#62;",
        "amp" => "&#38;",
        "quot" => "&#34;",
        "apos" => "&#39;"
    ];
    return $named[$entity] ?? htmlentities("&$entity;", ENT_NOQUOTES | ENT_XML1);
}

function parse_hocr(string $hocr): array
{
    // Replace named entities with numeric ones for DOM compatibility
    $hocr = preg_replace_callback('/&([a-zA-Z]+);/', function ($m) {
        return convert_entity($m[1]);
    }, $hocr);

    libxml_use_internal_errors(true);  // Suppress HTML parsing errors
    $dom = new DOMDocument();
    $dom->loadHTML($hocr, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $events = [];

    $walker = function (DOMNode $node) use (&$walker, &$events) {
        if (!($node instanceof DOMElement)) return;

        $class = $node->getAttribute("class");
        $boxType = BoxType::fromHocrClass($class);

        if ($class === "alternatives") {
            // hOCR alternative parsing support
            // See if we’re inside a current word
            // Skipped for now — can be added if needed
        }

        if ($boxType !== null) {
            // Extract bbox and other properties
            $props = [];
            if ($node->hasAttribute("title")) {
                preg_match_all('/(bbox|ppageno|x_source|image) "?([^";]+)"?/', $node->getAttribute("title"), $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $props[$m[1]] = $m[2];
                }
            }

            $evt = new ParseEvent(EventKind::START, $boxType);
            if (isset($props['x_source'], $props['ppageno'], $props['image'])) {
                $evt->pageId = $props['x_source'] ?? $props['ppageno'] ?? $props['image'];
            }

            if (isset($props['bbox'])) {
                [$x1, $y1, $x2, $y2] = array_map('intval', explode(' ', $props['bbox']));
                $evt->x = $x1;
                $evt->y = $y1;
                $evt->width = $x2 - $x1;
                $evt->height = $y2 - $y1;
            }

            if ($boxType === BoxType::WORD) {
                $evt->text = $node->textContent;
            }

            $events[] = $evt;
        }

        // Traverse children
        foreach ($node->childNodes as $child) {
            $walker($child);
        }

        // Emit END tag if it's a box type
        if ($boxType !== null) {
            $events[] = new ParseEvent(EventKind::END, $boxType);
            // Check for tail text (hOCR-specific, usually after a word)
            if ($node->nextSibling && $node->nextSibling->nodeType === XML_TEXT_NODE) {
                $tail = $node->nextSibling->nodeValue;
                if (trim($tail) !== '') {
                    $events[] = new ParseEvent(EventKind::TEXT, null, text: $tail);
                }
            }
        }
    };

    $walker($dom->documentElement);
    return $events;
}


function parse_alto($alto)
{
    $events = [];
    $useRelative = false;
    $relativeReference = null;
    $curWord = null;

    // Load as XML
    $reader = new XMLReader();
    $reader->XML($alto);
    $depth = 0;

    while ($reader->read()) {
        $nodeType = $reader->nodeType;
        $tagName = $reader->localName;

        if ($nodeType === XMLReader::ELEMENT) {
            $depth++;
            if ($tagName === "SP") {
                $ev = new ParseEvent(EventKind::TEXT, null);
                $ev->text = " ";
                $events[] = $ev;
            }

            if ($tagName === "MeasurementUnit" && $reader->readInnerXML() !== "pixel") {
                $useRelative = true;
            }

            if ($tagName === "ALTERNATIVE" && $curWord !== null) {
                $reader->read(); // Move to text node
                $curWord->text .= "⇿" . $reader->value;
            }

            $boxType = BoxType::fromAltoTag($tagName);
            if ($boxType === null) {
                continue;
            }

            $ev = new ParseEvent(EventKind::START, $boxType);

            if ($tagName === "Page") {
                if ($reader->getAttribute("ID")) {
                    $ev->pageId = $reader->getAttribute("ID");
                }
                if ($useRelative) {
                    $relativeReference = [
                        (int)$reader->getAttribute("WIDTH"),
                        (int)$reader->getAttribute("HEIGHT")
                    ];
                }
            }

            $hasCoords = $reader->getAttribute("HPOS") &&
                $reader->getAttribute("VPOS") &&
                $reader->getAttribute("WIDTH") &&
                $reader->getAttribute("HEIGHT");

            if ($hasCoords) {
                $ev->x = (float)$reader->getAttribute("HPOS");
                $ev->y = (float)$reader->getAttribute("VPOS");
                $ev->width = (float)$reader->getAttribute("WIDTH");
                $ev->height = (float)$reader->getAttribute("HEIGHT");

                if ($useRelative && $relativeReference) {
                    [$rw, $rh] = $relativeReference;
                    $ev->x /= $rw;
                    $ev->y /= $rh;
                    $ev->width /= $rw;
                    $ev->height /= $rh;
                } else {
                    $ev->x = (int)$ev->x;
                    $ev->y = (int)$ev->y;
                    $ev->width = (int)$ev->width;
                    $ev->height = (int)$ev->height;
                }
            }

            if ($boxType === BoxType::WORD) {
                $ev->text = $reader->getAttribute("CONTENT");
                $subsType = $reader->getAttribute("SUBS_TYPE");
                if ($subsType === "HypPart1") {
                    $ev->text .= "\xad";
                }
                $curWord = $ev;
                continue; // Wait to yield until END
            }

            $events[] = $ev;
        } elseif ($nodeType === XMLReader::END_ELEMENT) {
            $boxType = BoxType::fromAltoTag($tagName);
            if ($boxType === null) {
                continue;
            }

            if ($boxType === BoxType::WORD && $curWord !== null) {
                $events[] = $curWord;
                $curWord = null;
            }

            $events[] = new ParseEvent(EventKind::END, $boxType);
            $depth--;
        }
    }

    return $events;
}

function generate_miniocr(array $events): string
{
    $output = ["<ocr>"];
    $lastHyphen = false;

    foreach ($events as $evt) {
        if ($evt->kind === EventKind::TEXT && $evt->text !== null) {
            if ($lastHyphen && trim($evt->text) === "") {
                continue;
            }
            $output[] = $evt->text;
            $lastHyphen = mb_substr($evt->text, -1) === "\xad";
            continue;
        }

        if ($evt->boxType === null) {
            continue;
        }

        $tag = BoxType::toMiniocrTag($evt->boxType);

        if ($evt->kind === EventKind::START) {
            $attribs = [];

            if ($evt->boxType === BoxType::PAGE) {
                if ($evt->pageId) {
                    $attribs[] = 'xml:id="' . htmlspecialchars($evt->pageId, ENT_QUOTES) . '"';
                }
                if (is_int($evt->width) && is_int($evt->height)) {
                    $attribs[] = 'wh="' . $evt->width . ' ' . $evt->height . '"';
                }
            } elseif ($evt->boxType === BoxType::WORD) {
                if (isset($evt->x, $evt->y, $evt->width, $evt->height)) {
                    if (is_float($evt->x) || is_float($evt->y)) {
                        $coords = implode(" ", array_map(fn($f) => substr(sprintf("%.4f", $f), 1), [
                            $evt->x,
                            $evt->y,
                            $evt->width,
                            $evt->height
                        ]));
                        $attribs[] = 'x=' . $coords;
                    } else {
                        $attribs[] = 'x="' . implode(" ", [$evt->x, $evt->y, $evt->width, $evt->height]) . '"';
                    }
                }
            }

            $line = '<' . $tag;
            if (!empty($attribs)) {
                $line .= ' ' . implode(" ", $attribs);
            }
            $line .= '>';
            $output[] = $line;

            if ($evt->boxType === BoxType::WORD && $evt->text !== null) {
                $lastHyphen = mb_substr($evt->text, -1) === "\xad";
                $output[] = htmlspecialchars($evt->text, ENT_NOQUOTES | ENT_SUBSTITUTE);
            }
        } elseif ($evt->kind === EventKind::END) {
            $output[] = "</$tag>";
            if ($evt->boxType === BoxType::LINE && !$lastHyphen) {
                $output[] = " ";
            }
        }
    }

    $output[] = "</ocr>";
    return implode("", $output);
}

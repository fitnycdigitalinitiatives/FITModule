<?php

namespace FITModule\Controller\IiifSearch\v2;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\RuntimeException;
use SolrClient;
use SolrClientException;
use SolrQuery;

class IiifSearchController extends AbstractActionController
{
    public function searchAction()
    {
        if (($settings = $this->settings()) && ($settings->get('fit_module_solr_connection')) && ($host = $settings->get('fit_module_solr_hostname')) && ($port = $settings->get('fit_module_solr_port')) && ($path = $settings->get('fit_module_solr_path')) && ($params = $this->params()->fromQuery()) && array_key_exists('q', $params) && ($q = $params['q']) && ($media_id = $this->params('media-id'))) {
            $response = $this->getResponse();
            $client = new SolrClient([
                'hostname' => $host,
                'port' => $port,
                'path' => $path,
                'login' => $settings->get('fit_module_solr_login') ? $settings->get('fit_module_solr_login') : "",
                'password' => $settings->get('fit_module_solr_password') ? $settings->get('fit_module_solr_password') : "",
                'wt' => 'json',
            ]);
            $solrQuery = new SolrQuery;
            $solrQuery->setQuery('ocr_text:"' . urldecode($q) . '"');
            $solrQuery->setHighlight(true);
            $solrQuery->setHighlightSnippets(4096);
            $solrQuery->addparam('hl.ocr.fl', 'ocr_text');
            $solrQuery->addparam('hl.ocr.absoluteHighlights', 'on');
            $solrQuery->addFilterQuery('media_id:' . $media_id);
            $solrQuery->addField('media_id');
            try {
                $solrQueryResponse = $client->query($solrQuery);
                $ocrHighlighting = $solrQueryResponse->getResponse()['ocrHighlighting'];
                $hlresp = [
                    'numTotal' => 0,
                    'snippets' => []
                ];
                foreach ($ocrHighlighting as $page_snips) {
                    foreach ($page_snips['ocr_text']['snippets'] as $snips) {
                        $hlresp['snippets'][] = $snips;
                    }
                    $hlresp['numTotal'] += $page_snips['ocr_text']['numTotal'];
                }
                $doc = [
                    "@context" => "http://iiif.io/api/search/2/context.json",
                    "id" => $this->url()->fromRoute(null, [], ['force_canonical' => true], true),
                    "type" => "AnnotationPage",
                    "items" => [],
                    "annotations" => [
                        [
                            "type" => "AnnotationPage",
                            "items" => [],
                        ]
                    ]
                ];
                $doc['@id'] = $this->url()->fromRoute(null, [], ['force_canonical' => true], true) . '?q=' . urlencode($q);
                $doc['within']['total'] = $hlresp['numTotal'];
                $ignored = [];
                foreach (array_keys($params) as $key) {
                    if ($key != "q") {
                        $ignored[] = $key;
                    }
                }
                $api = $this->api();
                $media = $api->read('media', $media_id)->getContent();
                // Check if the index needs to be offset
                $index_offset = is_numeric($media->mediaData()['index_offset']) ? $media->mediaData()['index_offset'] : 0;
                $doc['within']['ignored'] = $ignored;
                // HL_PAT = re.compile("<em>(.+?)</em>");
                foreach ($hlresp['snippets'] as $supidx => $snip) {
                    $text = $snip['text'];
                    preg_match_all('#<em>(.*?)<\/em>#', $text, $matches, PREG_OFFSET_CAPTURE);
                    $hl_textmatches = $matches;
                    foreach ($snip['highlights'] as $idx => $hlspan) {
                        $hl_match = $hl_textmatches[0][$idx];
                        try {
                            $before = str_replace(["<em>", "</em>"], "", substr($text, 0, $hl_match[1]));
                            $after = str_replace(["<em>", "</em>"], "", substr($text, $hl_match[1] + strlen($hl_match[0])));
                        } catch (Exception $e) {
                            $before = null;
                            $after = null;
                        }
                        $hl_text = $hl_textmatches[1][$idx][0];
                        $anno_ids = [];
                        foreach ($hlspan as $subidx => $hlbox) {
                            $region = $snip['regions'][$hlbox['parentRegionIdx']];
                            $page = $snip['pages'][$region['pageIdx']]['id'];
                            $pageIndex = (int) preg_replace('/[^0-9]/', '', $page) + $index_offset;
                            $x = $hlbox['ulx'];
                            $y = $hlbox['uly'];
                            $w = $hlbox['lrx'] - $hlbox['ulx'];
                            $h = $hlbox['lry'] - $hlbox['uly'];
                            $ident = $this->url()->fromRoute('iiif-presentation-3/media/manifest', ['media-id' => $media_id], ['force_canonical' => true]) . '/annotation/' . urlencode($q) . '-' . $supidx . '-' . $idx . '-' . $subidx;
                            $anno_ids[] = $ident;
                            $anno = [
                                "id" => $ident,
                                "type" => "Annotation",
                                "motivation" => "painting",
                                "body" => [
                                    "type" => "TextualBody",
                                    "value" => $hlbox['text'],
                                    "format" => "text/plain",
                                ],
                                "target" => $this->url()->fromRoute('iiif-presentation-3/media/canvas', ['media-id' => $media_id, 'index' => $pageIndex], ['force_canonical' => true]) . '#xywh=' . $x . ',' . $y . ',' . $w . ',' . $h
                            ];
                            $doc['items'][] = $anno;
                        }
                        $doc['annotations'][0]['items'][0] = [
                            'id' => $this->url()->fromRoute('iiif-presentation-3/media/manifest', ['media-id' => $media_id], ['force_canonical' => true]) . '/annotation/match-' . $supidx,
                            'annotations' => $anno_ids,
                            'match' => $hl_text,
                            'before' => $before,
                            'after' => $after,
                        ];
                    }
                }
                $response->setContent(json_encode($doc, JSON_PRETTY_PRINT));
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                $response->getHeaders()->addHeaderLine('Access-Control-Allow-Origin', '*');
                return $response;
            } catch (SolrClientException $e) {
                $error = array('error' => ["code" => $e->getCode(), "message" => $e->getMessage()]);
                $response->setStatusCode(500);
                $response->setContent(json_encode($error));
                $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
                return $response;
            }
        } else {
            throw new RuntimeException("Invalid Page");
        }
    }
}

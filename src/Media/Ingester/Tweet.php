<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\Text;
use Zend\Http\Client;
use Zend\View\Renderer\PhpRenderer;

class Tweet implements IngesterInterface
{
    protected $client;
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
    public function getLabel()
    {
        return 'Tweet'; // @translate
    }
    public function getRenderer()
    {
        return 'fitmodule_tweet';
    }
    public function form(PhpRenderer $view, array $options = [])
    {
        $input = new Text('o:media[__index__][o:source]');
        $input->setOptions([
            'label' => 'Tweet URL', // @translate
            'info' => 'URL for the tweet to embed.', // @translate
        ]);
        $input->setAttributes([
            'required' => true,
        ]);
        return $view->formRow($input);
    }
    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        // Validate the request data.
        $data = $request->getContent();
        if (!isset($data['o:source'])) {
            $errorStore->addError('o:source', 'No tweet URL specified');
            return;
        }
        // Validate the URL.
        $isMatch = preg_match('/^https:\/\/twitter\.com\/[\w]+\/status\/[\d]+$/', $data['o:source']);
        if (!$isMatch) {
            $errorStore->addError('o:source', sprintf(
                'Invalid tweet URL: %s',
                $data['o:source']
            ));
            return;
        }
        // Get the oEmbed JSON.
        $url = sprintf('https://publish.twitter.com/oembed?url=%s', urlencode($data['o:source']));
        $response = $this->client->setUri($url)->send();
        if (!$response->isOk()) {
            $errorStore->addError('o:source', sprintf(
                'Error reading tweet: %s (%s)',
                $response->getReasonPhrase(),
                $response->getStatusCode()
            ));
            return false;
        }
        // Set the Media source and data.
        $media->setSource($data['o:source']);
        $media->setData(json_decode($response->getBody(), true));
    }
}

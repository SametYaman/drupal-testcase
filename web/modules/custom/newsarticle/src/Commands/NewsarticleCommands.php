<?php
namespace Drupal\newsarticle\Commands;

use Drupal\newsarticle\Controller\NewsarticleController;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class NewsarticleCommands extends DrushCommands
{
    /**
     * The HTTP client to fetch the feed data with.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;
  
    /**
     * Constructor for NewsarticleCommands.
     *
     * @param \GuzzleHttp\ClientInterface $http_client
     *   A Guzzle client object.
     */
    public function __construct(ClientInterface $http_client)
    {
        $this->httpClient = $http_client;
    }
  
    /**
     * Import Newsarticle from API.
     *
     * @command import-news
     * @aliases import_news
     */
    public function importNewsarticle()
    {
        $response = NewsarticleController::importNewsarticle($this->httpClient, false);
        if (is_iterable($response)) {
            if (isset($response[0]) && $response[0] == false && isset($response[1])) {
                $this->io()->error($response[1]);
            } else if (isset($response['insert']) && isset($response['update'])) {
                $this->io()->success('Newsarticle Inserted: ' . $response['insert'] . ' - Newsarticle Updated: ' . $response['update']);
            }
        } else {
            $this->io()->error('$response must be array.');
        }
    }
}
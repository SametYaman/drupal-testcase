<?php
namespace Drupal\newsarticle\Commands;

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
class NewsarticleCommands extends DrushCommands {
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
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }
  
  /**
   * Import Newsarticle from API.
   *
   * @command import-news
   * @aliases import_news
   */
  public function importNewsarticle() {
    try {
      $response = $this->httpClient->request('GET', 'https://riad-news-api.vercel.app/api/news');
      $statusCode = $response->getStatusCode();
      
      if ($statusCode == 200) {
        $body = $response->getBody();
        $data = json_decode($body, true);

        if ($data['status'] == 'success' && is_iterable($data) && count($data['data']) > 0) {
          $data = $data['data'];

          foreach ($data as $newsItem) {
            $this->output()->writeln("");
            $this->output()->writeln($newsItem['source']);
            $this->output()->writeln($newsItem['title']);
            $this->output()->writeln('------------------------------');
          }

          $this->output()->writeln("Total Newsarticle:" . count($data));
        }
      }
      else {
        $this->output()->writeln('API request failed.');
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln('An error occurred during the API request: ' . $e->getMessage());
    }
  }
}
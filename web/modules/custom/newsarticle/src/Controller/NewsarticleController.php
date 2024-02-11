<?php declare(strict_types = 1);

namespace Drupal\newsarticle\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\newsarticle\Entity\Newsarticle;
use GuzzleHttp\ClientInterface;

/**
 * Returns responses for Newsarticle routes.
 */
final class NewsarticleController extends ControllerBase {
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
   * Builds the response.
   */
  public function build() {
    $build = [
      '#markup' => $this->t('No action.'),
      '#cache' => [
        'max-age' => 0
      ]
    ];
    try {
      $response = $this->httpClient->request('GET', 'https://riad-news-api.vercel.app/api/news');
      $statusCode = $response->getStatusCode();
      if ($statusCode == 200) {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if ($data['status'] == 'success' && is_iterable($data) && count($data['data']) > 0) {
          $data = $data['data'];
          $result = [];
          foreach ($data as $newsItem) {
            $result[] = self::createNewsarticle($newsItem);
          }
          $resultCounts = [
            'insert' => 0,
            'update' => 0
          ];
          foreach ($result as $resultItem) {
            if ($resultItem[0] == 'insert' || $resultItem[0] == 'update') {
              $resultCounts[$resultItem[0]] = $resultCounts[$resultItem[0]] + 1;
            }
          }
          
          \Drupal::logger('newsarticle')->notice("Total Newsarticle: " . count($data) . ' - Inserted: ' . $resultCounts['insert'] . ' - Updated: ' . $resultCounts['update']);
          $build['#markup'] = "Total Newsarticle: " . count($data) . '<br>Inserted: ' . $resultCounts['insert'] . '<br>Updated: ' . $resultCounts['update'];
          return $build;
        }
      }
      else {
        \Drupal::logger('newsarticle')->error('API request failed.');
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('newsarticle')->error('An error occurred during the API request: ' . $e->getMessage());
      $build['#markup'] = 'No action.<br><b>Error:</b><br><code>' . $e->getMessage() . '</code>';
    }

    return $build;
  }

  /**
   * Generate Random Password for Author Accounts.
   */
  public static function generateRandomPassword($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomPassword = '';
    for ($i = 0; $i < $length; $i++) {
      $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomPassword;
  }

  /**
   * Returns the Author ID if it exists; otherwise creates and returns it.
   */
  public static function getAuthorId($source = '') {
    // If source is empty, return admin user.
    if ($source == '' || empty($source)) {
      return 1;
    }
    $userStorage = \Drupal::entityTypeManager()->getStorage('user');

    $user = $userStorage->loadByProperties([
      'name' => $source,
    ]);

    if (is_array($user) && count($user) > 0) {
      return reset($user)->id();
    }
    else {
      $user = [
        'name' => $source,
        'pass' => self::generateRandomPassword(20),
        'status' => TRUE,
      ];
      $user = $userStorage->create($user);
      $user->save();
      return $user->id();
    }
  }

  /**
   * Create a newsarticle.
   */
  public static function createNewsarticle($newsItem = '') {
    if ($newsItem == '' || !is_iterable($newsItem) || !isset($newsItem['title']) || empty($newsItem['title']) || !isset($newsItem['source']) || empty($newsItem['source'])) {
      return FALSE;
    }
    try {
      $authorId = self::getAuthorId($newsItem['source']);
      $newsarticleStorage = \Drupal::entityTypeManager()->getStorage('newsarticle');
      $newsarticle = $newsarticleStorage->loadByProperties([
        'label' => $newsItem['title'],
        'uid' => $authorId
      ]);
  
      if (is_array($newsarticle) && count($newsarticle) > 0) {
        // return reset($newsarticle)->id();
        $newsarticle = reset($newsarticle);
        if ($newsarticle instanceof Newsarticle) {
          $updateReq = FALSE;
          if ($newsarticle->get('body')->value != $newsItem['description']) {
            $updateReq = TRUE;
            $newsarticle->set('body', [
              'value' => $newsItem['description'],
              'summary' => '',
              'format' => 'basic_html'
            ]);
          }
          if ($newsarticle->get('created')->value != strtotime($newsItem['pubDate'])) {
            $updateReq = TRUE;
            $newsarticle->set('created', strtotime($newsItem['pubDate']));
          }
          
          if ($updateReq) {
            $newsarticle->save();
          }
        }
        return [
          'update',
          $newsarticle
        ];
      } else {
        // date field insert?.

        $newsarticle = [
          'label' => $newsItem['title'],
          'body' => [
            'value' => $newsItem['description'],
            'summary' => '',
            'format' => 'basic_html'
          ],
          'uid' => $authorId,
          'created' => strtotime($newsItem['pubDate']),
          'status' => TRUE,
        ];
        $newsarticle = $newsarticleStorage->create($newsarticle);
        $newsarticle->save();
        return [
          'insert',
          $newsarticle
        ];
      }
      
    } catch (\Exception $e) {
      \Drupal::logger('newsarticle')->error('createNewsarticle:' . $e->getMessage());
    }
  }

  public static function removeAll() {
    $newsarticle_entity_storage = \Drupal::entityTypeManager()->getStorage('newsarticle');
    $nids = array_values(\Drupal::entityQuery('newsarticle')->accessCheck(FALSE)->execute());
    $entities = $newsarticle_entity_storage->loadMultiple($nids);
    $newsarticle_entity_storage->delete($entities);

    $database = \Drupal::database();
    $database->query("ALTER TABLE `newsarticle` AUTO_INCREMENT=1")->execute();
    $database->query("ALTER TABLE `newsarticle_revision` AUTO_INCREMENT=1")->execute();

    return [
      '#markup' => t('All Newsarticle removed.'),
      '#cache' => [
        'max-age' => 0
      ]
    ];
  }


}

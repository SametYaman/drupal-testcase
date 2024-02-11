<?php declare(strict_types = 1);

namespace Drupal\newsarticle\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\newsarticle\Entity\Newsarticle;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Newsarticle routes.
 */
final class NewsarticleController extends ControllerBase
{
    /**
     * The HTTP client to fetch the feed data with.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * The entity type manager service.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * The pager manager service.
     *
     * @var \Drupal\Core\Pager\PagerManagerInterface
     */
    protected $pagerManager;
  
    /**
     * Constructor for NewsarticleCommands.
     *
     * @param \GuzzleHttp\ClientInterface $http_client
     *   A Guzzle client object.
     */
    public function __construct(ClientInterface $http_client, EntityTypeManagerInterface $entity_type_manager, PagerManagerInterface $pager_manager)
    {
        $this->httpClient = $http_client;
        $this->entityTypeManager = $entity_type_manager;
        $this->pagerManager = $pager_manager;
    }
  
    /**
     * Builds the response.
     */
    public function build()
    {
        return [
        '#markup' => $this->importNewsarticle($this->httpClient, true),
        '#cache' => [
        'max-age' => 0
        ]
        ];
    }
 
    /**
     * Controller route callback.
     */
    public function buildOverviewPage(Request $request)
    {
        $build = [];
        $userStorage = \Drupal::entityTypeManager()->getStorage('user');
        $newsarticleStorage = \Drupal::entityTypeManager()->getStorage('newsarticle');

        $query = \Drupal::entityQuery('newsarticle')->accessCheck(false);
        $query->sort('created', 'DESC');

        // User Exposed Filter Options.
        $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
        $user_options = [];
        foreach ($users as $user) {
            if (!$user->hasRole('administrator') && $user->id() != 0) {
                $user_options[$user->id()] = $user->getDisplayName();
            }
        }

        $uid = \Drupal::request()->query->get('uid');
        if (!empty($uid)) {
            $query->condition('uid', $uid);
        }
        $pager = $query->pager(21);
        $result = $query->execute();
        $newsarticles = [];
        foreach ($result as $newsarticle_id) {
            $newsarticle = $newsarticleStorage->load($newsarticle_id);
            $authorId = $newsarticle->get('uid')->target_id;
            $author = $userStorage->load($authorId);
            $newsarticles[] = [
            'title' => $newsarticle->get('label')->value,
            'description' => $newsarticle->get('body')->value,
            'pubDate' => $newsarticle->get('created')->value,
            'author' => ($author instanceof User) ? $author->getDisplayName() : $authorId,
            'link' => $newsarticle->toUrl()->toString(),
            ];
        }

        $build = [
        '#theme' => 'newsarticle_list',
        '#newsarticles' => $newsarticles,
        '#user_options' => $user_options,
        '#selected_user' => (is_numeric($uid) ? $uid : ''),
        '#pager' => [
        '#type' => 'pager',
        ],
        '#cache' => [
        'max-age' => 0
        ]
        ];

        return $build;
    }
  
    /**
     * Import Newsarticle from API.
     */
    public static function importNewsarticle($httpClient, $buildResponse = false)
    {
        if ($buildResponse) {
            $response = 'No action.';
        }
        try {
            $response = $httpClient->request('GET', 'https://riad-news-api.vercel.app/api/news');
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
                    if ($buildResponse) {
                        $response = "Total Newsarticle: " . count($data) . '<br>Inserted: ' . $resultCounts['insert'] . '<br>Updated: ' . $resultCounts['update'];
                        return $response;
                    }
                    return $resultCounts;
                }
            }
            else {
                \Drupal::logger('newsarticle')->error('API request failed.');
                if (!$buildResponse) {
                    return [false, 'API request failed.'];
                }
            }
        }
        catch (\Exception $e) {
            \Drupal::logger('newsarticle')->error('An error occurred during the API request: ' . $e->getMessage());
            if ($buildResponse) {
                $response = 'No action.<br><b>Error:</b><br><code>' . $e->getMessage() . '</code>';
                return $response;
            }
            return [false, $e->getMessage()];
        }
        return false;
    }

    /**
     * Generate Random Password for Author Accounts.
     */
    public static function generateRandomPassword($length = 20)
    {
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
    public static function getAuthorId($source = '')
    {
        // If source is empty, return admin user.
        if ($source == '' || empty($source)) {
            return 1;
        }
        $userStorage = \Drupal::entityTypeManager()->getStorage('user');

        $user = $userStorage->loadByProperties(
            [
            'name' => $source,
            ]
        );

        if (is_array($user) && count($user) > 0) {
            return reset($user)->id();
        }
        else {
            $user = [
            'name' => $source,
            'pass' => self::generateRandomPassword(20),
            'status' => true,
            ];
            $user = $userStorage->create($user);
            $user->save();
            return $user->id();
        }
    }

    /**
     * Insert all newsarticle.
     */
    public static function createNewsarticle($newsItem = '')
    {
        if ($newsItem == '' || !is_iterable($newsItem) || !isset($newsItem['title']) || empty($newsItem['title']) || !isset($newsItem['source']) || empty($newsItem['source'])) {
            return false;
        }
        try {
            $authorId = self::getAuthorId($newsItem['source']);
            $newsarticleStorage = \Drupal::entityTypeManager()->getStorage('newsarticle');
            $newsarticle = $newsarticleStorage->loadByProperties(
                [
                'label' => $newsItem['title'],
                'uid' => $authorId
                ]
            );
  
            if (is_array($newsarticle) && count($newsarticle) > 0) {
                $newsarticle = reset($newsarticle);
                if ($newsarticle instanceof Newsarticle) {
                    $updateReq = false;
                    if ($newsarticle->get('body')->value != $newsItem['description']) {
                        $updateReq = true;
                        $newsarticle->set(
                            'body', [
                            'value' => $newsItem['description'],
                            'summary' => '',
                            'format' => 'basic_html'
                            ]
                        );
                    }
                    if ($newsarticle->get('created')->value != strtotime($newsItem['pubDate'])) {
                        $updateReq = true;
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
                $newsarticle = [
                'label' => $newsItem['title'],
                'body' => [
                'value' => $newsItem['description'],
                'summary' => '',
                'format' => 'basic_html'
                ],
                'uid' => $authorId,
                'created' => strtotime($newsItem['pubDate']),
                'status' => true,
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

    /**
     * Remove all newsarticle.
     */
    public static function removeAll()
    {
        $newsarticle_entity_storage = \Drupal::entityTypeManager()->getStorage('newsarticle');
        $nids = array_values(\Drupal::entityQuery('newsarticle')->accessCheck(false)->execute());
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

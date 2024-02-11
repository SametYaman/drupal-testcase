<?php declare(strict_types = 1);

namespace Drupal\newsarticle;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the newsarticle entity type.
 */
final class NewsarticleListBuilder extends EntityListBuilder
{

    /**
     * {@inheritdoc}
     */
    public function buildHeader(): array
    {
        $header['id'] = $this->t('ID');
        $header['label'] = $this->t('Label');
        $header['status'] = $this->t('Status');
        $header['uid'] = $this->t('Author');
        $header['created'] = $this->t('Publication Date');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity): array
    {
        /**
   * @var \Drupal\newsarticle\NewsarticleInterface $entity 
*/
        $row['id'] = $entity->id();
        $row['label'] = $entity->toLink();
        $row['status'] = $entity->get('status')->value ? $this->t('Enabled') : $this->t('Disabled');
        $username_options = [
        'label' => 'hidden',
        'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
        ];
        $row['uid']['data'] = $entity->get('uid')->view($username_options);
        $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
        return $row + parent::buildRow($entity);
    }

}

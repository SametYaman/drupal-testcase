<?php declare(strict_types = 1);

namespace Drupal\newsarticle;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the newsarticle entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class NewsarticleAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return match($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view newsarticle', 'administer newsarticle'], 'OR'),
      'update' => AccessResult::allowedIfHasPermissions($account, ['edit newsarticle', 'administer newsarticle'], 'OR'),
      'delete' => AccessResult::allowedIfHasPermissions($account, ['delete newsarticle', 'administer newsarticle'], 'OR'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create newsarticle', 'administer newsarticle'], 'OR');
  }

}

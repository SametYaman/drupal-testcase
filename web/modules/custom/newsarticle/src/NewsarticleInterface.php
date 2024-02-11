<?php declare(strict_types = 1);

namespace Drupal\newsarticle;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a newsarticle entity type.
 */
interface NewsarticleInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}

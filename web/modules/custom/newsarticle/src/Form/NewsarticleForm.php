<?php declare(strict_types = 1);

namespace Drupal\newsarticle\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the newsarticle entity edit forms.
 */
final class NewsarticleForm extends ContentEntityForm
{

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state): int
    {
        $result = parent::save($form, $form_state);

        $message_args = ['%label' => $this->entity->toLink()->toString()];
        $logger_args = [
        '%label' => $this->entity->label(),
        'link' => $this->entity->toLink($this->t('View'))->toString(),
        ];

        switch ($result) {
        case SAVED_NEW:
            $this->messenger()->addStatus($this->t('New newsarticle %label has been created.', $message_args));
            $this->logger('newsarticle')->notice('New newsarticle %label has been created.', $logger_args);
            break;

        case SAVED_UPDATED:
            $this->messenger()->addStatus($this->t('The newsarticle %label has been updated.', $message_args));
            $this->logger('newsarticle')->notice('The newsarticle %label has been updated.', $logger_args);
            break;

        default:
            throw new \LogicException('Could not save the entity.');
        }

        $form_state->setRedirectUrl($this->entity->toUrl());

        return $result;
    }

}

<?php

namespace App\Form;

use App\Entity\IngestionQueueItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IngestionQueueItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'ingestion.status.queued' => IngestionQueueItem::STATUS_QUEUED,
                    'ingestion.status.processing' => IngestionQueueItem::STATUS_PROCESSING,
                    'ingestion.status.indexed' => IngestionQueueItem::STATUS_INDEXED,
                    'ingestion.status.failed' => IngestionQueueItem::STATUS_FAILED,
                    'ingestion.status.download_failed' => IngestionQueueItem::STATUS_DOWNLOAD_FAILED,
                ],
                'choice_translation_domain' => 'messages',
                'label' => 'ingestion.queue.fields.status',
            ])
            ->add('ragMessage', TextareaType::class, [
                'required' => false,
                'label' => 'ingestion.queue.fields.rag_message',
            ])
            ->add('storagePath', TextType::class, [
                'required' => false,
                'label' => 'ingestion.queue.fields.storage_path',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IngestionQueueItem::class,
        ]);
    }
}

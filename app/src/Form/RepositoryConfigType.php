<?php

namespace App\Form;

use App\Entity\RepositoryConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepositoryConfigType extends AbstractType
{
    public function __construct(protected string $githubDefaultBranch)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('owner', TextType::class)
            ->add('name', TextType::class)
            ->add('token', TextType::class, [
                'help' => 'Personal access token with repo and contents permissions. Stored encrypted.',
            ])
            ->add('defaultBranch', TextType::class, [
                'required' => false,
                'help' => 'Optional override when the repository uses a non-default branch.',
                'empty_data' => $this->githubDefaultBranch
            ])
            ->add('save', SubmitType::class, ['label' => 'Save configuration']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepositoryConfig::class,
        ]);
    }
}

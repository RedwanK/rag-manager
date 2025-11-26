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
            ->add('owner', TextType::class, [
                'label' => 'repository.fields.owner',
            ])
            ->add('name', TextType::class, [
                'label' => 'repository.fields.name',
            ])
            ->add('token', TextType::class, [
                'label' => 'repository.fields.token',
                'help' => 'repository.help.token',
            ])
            ->add('defaultBranch', TextType::class, [
                'required' => false,
                'label' => 'repository.fields.default_branch',
                'help' => 'repository.help.default_branch',
                'empty_data' => $this->githubDefaultBranch
            ])
            ->add('save', SubmitType::class, ['label' => 'form.actions.save']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RepositoryConfig::class,
        ]);
    }
}

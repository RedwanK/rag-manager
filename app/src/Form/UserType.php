<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Configure creation and edition of user accounts with role and status management.
 */
class UserType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.fields.email',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.fields.roles',
                'choices' => [
                    'user.roles.admin' => 'ROLE_ADMIN',
                    'user.roles.reviewer' => 'ROLE_REVIEWER',
                    'user.roles.viewer' => 'ROLE_VIEWER',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'user.fields.status',
                'choices' => [
                    'user.status.active' => 'active',
                    'user.status.inactive' => 'inactive',
                ],
            ]);

        if ($options['include_password']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'user.fields.password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'user.fields.password_confirm',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'user.validation.password_mismatch',
                'constraints' => [
                    new NotBlank(message: 'user.validation.password_required'),
                    new Length(min: 8, minMessage: 'user.validation.password_length'),
                ],
            ]);
        }

        $builder->add('save', SubmitType::class, [
            'label' => 'form.actions.save',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'include_password' => false,
            'translation_domain' => 'messages',
        ]);
    }
}

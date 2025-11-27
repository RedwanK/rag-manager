<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Dedicated form for admin-driven password resets.
 */
class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'user.fields.password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'user.fields.password_confirm',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'mapped' => false,
                'invalid_message' => 'user.validation.password_mismatch',
                'constraints' => [
                    new NotBlank(message: 'user.validation.password_required'),
                    new Length(min: 8, minMessage: 'user.validation.password_length'),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'form.actions.reset_password',
            ]);
    }
}

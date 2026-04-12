<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => 'Nom',
                    'data-validate' => 'required|alpha|minlength:2|maxlength:100',
                    'data-label' => 'Nom',
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Length(min: 2, max: 100, minMessage: 'Minimum 2 caracteres.', maxMessage: 'Maximum 100 caracteres.'),
                    new Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/', message: 'Le nom ne doit contenir que des lettres.'),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => 'Prenom',
                    'data-validate' => 'required|alpha|minlength:2|maxlength:100',
                    'data-label' => 'Prenom',
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Length(min: 2, max: 100, minMessage: 'Minimum 2 caracteres.', maxMessage: 'Maximum 100 caracteres.'),
                    new Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/', message: 'Le prenom ne doit contenir que des lettres.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => 'email@exemple.com',
                    'inputmode' => 'email',
                    'data-validate' => 'required|email|maxlength:150',
                    'data-label' => 'Email',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Email(message: 'Email invalide.'),
                    new Assert\Length(max: 150, maxMessage: 'Maximum 150 caracteres.'),
                ],
            ])
            ->add('numTel', TextType::class, [
                'label' => 'Numero de telephone',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => '12345678',
                    'inputmode' => 'tel',
                    'data-validate' => 'required|digits|length:8',
                    'data-label' => 'Numero de telephone',
                    'autocomplete' => 'tel',
                    'minlength' => '8',
                    'maxlength' => '8',
                    'pattern' => '[0-9]{8}',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^\d{8}$/',
                        message: 'Le numéro de téléphone doit contenir exactement 8 chiffres.'
                    ),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => '********',
                    'data-validate' => 'required|minlength:8|maxlength:255',
                    'data-label' => 'Mot de passe',
                    'autocomplete' => 'new-password',
                    'minlength' => '8',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Length(
                        min: 8,
                        max: 255,
                        minMessage: 'Mot de passe trop court (8 caracteres minimum).',
                        maxMessage: 'Mot de passe trop long.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}

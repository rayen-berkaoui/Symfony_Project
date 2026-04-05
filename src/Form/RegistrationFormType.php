<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
            ->add('numTel', IntegerType::class, [
                'label' => 'Numero de telephone',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => 'Numero de telephone',
                    'inputmode' => 'tel',
                    'data-validate' => 'required|phone|minlength:6|maxlength:15',
                    'data-label' => 'Numero de telephone',
                    'autocomplete' => 'tel',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Positive(message: 'Numero invalide.'),
                    new Assert\Range(min: 1, max: 2147483647, notInRangeMessage: 'Numero invalide.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => '********',
                    'data-validate' => 'required|minlength:6|maxlength:255',
                    'data-label' => 'Mot de passe',
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ce champ est obligatoire.'),
                    new Assert\Length(
                        min: 6,
                        max: 255,
                        minMessage: 'Mot de passe trop court (6 caracteres minimum).',
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

<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [new Assert\Length(min: 6, max: 255, minMessage: 'Minimum 6 caracteres.', maxMessage: 'Maximum 255 caracteres.')];
        if ($options['require_password']) {
            $passwordConstraints[] = new Assert\NotBlank(message: 'Ce champ est obligatoire.');
        }

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
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'nom',
                'label' => 'Role',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'data-validate' => 'required',
                    'data-label' => 'Role',
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'Ce champ est obligatoire.'),
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'row_attr' => ['class' => 'form-row'],
                'choices' => [
                    'ACTIF' => Utilisateur::STATUT_ACTIF,
                    'BLOQUE' => Utilisateur::STATUT_BLOQUE,
                    'EN_ATTENTE' => Utilisateur::STATUT_EN_ATTENTE,
                ],
                'attr' => [
                    'data-validate' => 'required',
                    'data-label' => 'Statut',
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'Ce champ est obligatoire.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'Mot de passe' : 'Nouveau mot de passe',
                'mapped' => false,
                'required' => $options['require_password'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => [
                    'placeholder' => $options['require_password']
                        ? 'Mot de passe'
                        : 'Nouveau mot de passe (optionnel)',
                    'data-validate' => $options['require_password'] ? 'required|minlength:6|maxlength:255' : 'minlength:6|maxlength:255',
                    'data-label' => 'Mot de passe',
                    'autocomplete' => $options['require_password'] ? 'new-password' : 'new-password',
                ],
                'constraints' => $passwordConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'require_password' => false,
        ]);
    }
}

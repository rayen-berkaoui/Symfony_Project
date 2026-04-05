<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Entity\Panier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('panier', EntityType::class, [
                'class' => Panier::class,
                'choice_label' => function(Panier $panier) {
                    $lieu = $panier->getLieuTouristique() ? $panier->getLieuTouristique()->getNom() : 'Lieu Info';
                    return 'Panier ID #' . $panier->getId() . ' - ' . $lieu;
                },
                'label' => 'LiÃ© Ã  quel Panier ?',
                'placeholder' => 'SÃ©lectionner un Panier',
                'required' => true,
                'attr' => ['class' => 'input-modern']
            ])
            ->add('datePaiement', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de Paiement',
                'choices' => [
                    'Carte bancaire' => 'Carte',
                    'Espèces' => 'Espèces',
                    'Virement bancaire' => 'Virement',
                    'PayPal' => 'PayPal',
                ],
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('statutPaiement', ChoiceType::class, [
                'label' => 'Statut du Paiement',
                'choices' => [
                    'En cours de paiement' => 'En cours de paiement',
                    'Payé' => 'Payé',
                    'Remboursé' => 'Remboursé',
                    'Annulé' => 'Annulé',
                ],
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('montantTotal', MoneyType::class, [
                'label' => 'Montant Total',
                'currency' => 'TND',
                'divisor' => 1,
                'scale' => 2,
                'attr' => ['class' => 'input-modern'],
            ])
            ->add('codeConfirmation', TextType::class, [
                'label' => 'Code de Confirmation',
                'attr' => ['class' => 'input-modern', 'readonly' => true],
            ])
            ->add('rating', IntegerType::class, [
                'label' => 'Note (1-5)',
                'required' => false,
                'attr' => ['class' => 'input-modern', 'min' => 1, 'max' => 5],
            ])
            ->add('reviewComment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['class' => 'input-modern', 'rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}

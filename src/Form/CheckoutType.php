<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de Paiement',
                'choices' => [
                    'Carte bancaire' => 'Carte',
                    'Espèces' => 'Espèces',
                    'Virement bancaire' => 'Virement',
                    'PayPal' => 'PayPal',
                ],
                'expanded' => true,
                'attr' => ['class' => 'space-y-3'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un mode de paiement'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}

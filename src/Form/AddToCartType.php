<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddToCartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $serviceKind = (string) $options['service_kind'];
        $showRooms = (bool) $options['show_rooms'];
        $maxGuestsPerRoom = max(1, (int) $options['max_guests_per_room']);

        $builder
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de début est obligatoire'),
                    new Assert\GreaterThanOrEqual('yesterday', message: "La date de début doit être aujourd'hui ou plus tard"),
                ],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'input-modern'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de fin est obligatoire'),
                ],
            ])
            ->add('nbAdultes', IntegerType::class, [
                'label' => "Nombre d'adultes",
                'data' => 1,
                'attr' => ['class' => 'input-modern', 'min' => 1, 'max' => 20],
                'constraints' => [
                    new Assert\NotBlank(message: "Le nombre d'adultes est obligatoire"),
                    new Assert\Positive(message: "Le nombre d'adultes doit être positif"),
                    new Assert\LessThanOrEqual(20, message: 'Maximum 20 adultes'),
                ],
            ])
            ->add('nbEnfants', IntegerType::class, [
                'label' => "Nombre d'enfants",
                'data' => 0,
                'attr' => ['class' => 'input-modern', 'min' => 0, 'max' => 20],
                'constraints' => [
                    new Assert\PositiveOrZero(message: "Le nombre d'enfants ne peut pas être négatif"),
                    new Assert\LessThanOrEqual(20, message: 'Maximum 20 enfants'),
                ],
            ]);

        if ($showRooms) {
            $builder->add('nbChambres', IntegerType::class, [
                'label' => 'Nombre de chambres',
                'data' => 1,
                'attr' => ['class' => 'input-modern', 'min' => 1, 'max' => 10],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nombre de chambres est obligatoire'),
                    new Assert\Positive(message: 'Le nombre de chambres doit être positif'),
                    new Assert\LessThanOrEqual(10, message: 'Maximum 10 chambres'),
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($serviceKind, $showRooms, $maxGuestsPerRoom): void {
            $data = $event->getData();
            $form = $event->getForm();

            if (!is_array($data)) {
                return;
            }

            $dateDebut = $data['dateDebut'] ?? null;
            $dateFin = $data['dateFin'] ?? null;
            $adultes = max(0, (int) ($data['nbAdultes'] ?? 0));
            $enfants = max(0, (int) ($data['nbEnfants'] ?? 0));
            $chambres = max(1, (int) ($data['nbChambres'] ?? 1));
            $voyageurs = $adultes + $enfants;

            if ($dateDebut instanceof \DateTimeInterface && $dateFin instanceof \DateTimeInterface && $dateFin <= $dateDebut) {
                $form->get('dateFin')->addError(new FormError('La date de fin doit être après la date de début.'));
            }

            if ($adultes < 1) {
                $form->get('nbAdultes')->addError(new FormError('Il faut au moins un adulte pour effectuer une réservation.'));
            }

            if ($showRooms && $serviceKind === 'hotel') {
                if ($voyageurs > ($chambres * $maxGuestsPerRoom)) {
                    $form->get('nbChambres')->addError(new FormError(sprintf('Capacité dépassée : %d chambre(s) permettent au maximum %d personne(s).', $chambres, $chambres * $maxGuestsPerRoom)));
                }

                if ($chambres > $voyageurs && $voyageurs > 0) {
                    $form->get('nbChambres')->addError(new FormError('Le nombre de chambres semble trop élevé par rapport au nombre de voyageurs.'));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'service_kind' => 'general',
            'show_rooms' => false,
            'max_guests_per_room' => 4,
        ]);
    }
}

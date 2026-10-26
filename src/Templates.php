<?php
declare(strict_types=1);

namespace Appc;

/**
 * Modèles d'établissement appliqués à la création d'une instance, pour un portail prêt à l'emploi :
 * message d'accueil, annonce de bienvenue, une consultation publiée avec ses thèmes, et un sondage
 * et des inscriptions en brouillon que l'administrateur adapte puis publie.
 * Tout est modifiable ensuite depuis l'espace administrateur.
 */
final class Templates
{
    public const DEFAULT = 'elementaire';

    private const WELCOME = [
        'title' => 'Bienvenue sur le portail des parents',
        'body'  => "Ce portail permet aux parents de l'établissement de proposer des idées, de répondre aux sondages et de s'inscrire comme bénévoles.\n\n"
                 . "Ni compte ni adresse e-mail : votre accès est mémorisé sur cet appareil. Seules les inscriptions bénévoles "
                 . "demandent un prénom (ou un pseudo). Restez factuel et ne citez ni enfant ni personne nommément.",
    ];

    private const MEETING_POLL = [
        'title'       => 'Date de la prochaine réunion des parents',
        'description' => 'Cochez toutes les dates qui vous conviennent.',
        'options'     => ['Mardi à 18 h 30', 'Jeudi à 18 h 30', 'Samedi à 10 h'],
    ];

    public const ALL = [
        'elementaire' => [
            'label'        => 'École élémentaire',
            'intro'        => "Bienvenue sur l'espace des parents d'élèves. Proposez vos idées, votez pour celles qui vous semblent prioritaires et retrouvez ici les dates importantes de l'année.",
            'consultation' => [
                'title'       => 'Idées pour l\'école',
                'description' => 'Les propositions les plus soutenues seront portées par les représentants des parents au prochain conseil d\'école.',
                'themes'      => [['Rythmes scolaires', 1], ['Organisation périscolaire', 2], ['Encadrement des enfants', 3], ['Protection des enfants', 4]],
            ],
            'poll'         => self::MEETING_POLL,
            'signup'       => [
                'title'       => 'Fête de l\'école',
                'description' => 'Merci d\'indiquer le ou les créneaux où vous pouvez aider.',
                'slots'       => [['Installation', 4], ['Buvette', 3], ['Stand gâteaux', 3], ['Jeux', 4], ['Rangement', 4]],
            ],
        ],
        'maternelle' => [
            'label'        => 'École maternelle',
            'intro'        => "Bienvenue sur l'espace des parents d'élèves. Proposez vos idées, votez pour celles qui vous semblent prioritaires et retrouvez ici les dates importantes de l'année.",
            'consultation' => [
                'title'       => 'Idées pour l\'école',
                'description' => 'Les propositions les plus soutenues seront portées par les représentants des parents au prochain conseil d\'école.',
                'themes'      => [['Accueil et adaptation', 2], ['Rythmes et sieste', 1], ['Cantine et périscolaire', 3], ['Sécurité et locaux', 5]],
            ],
            'poll'         => self::MEETING_POLL,
            'signup'       => [
                'title'       => 'Fête de l\'école',
                'description' => 'Merci d\'indiquer le ou les créneaux où vous pouvez aider.',
                'slots'       => [['Installation', 4], ['Atelier maquillage', 2], ['Buvette', 3], ['Rangement', 4]],
            ],
        ],
        'college' => [
            'label'        => 'Collège',
            'intro'        => "Bienvenue sur l'espace des parents d'élèves du collège. Proposez vos idées, votez pour celles qui vous semblent prioritaires et retrouvez ici les dates importantes de l'année.",
            'consultation' => [
                'title'       => 'Idées pour le collège',
                'description' => 'Les propositions les plus soutenues seront portées par les représentants des parents au prochain conseil d\'administration.',
                'themes'      => [['Vie scolaire', 1], ['Restauration', 3], ['Orientation', 6], ['Bien-être et harcèlement', 4]],
            ],
            'poll'         => self::MEETING_POLL,
            'signup'       => [
                'title'       => 'Forum des métiers',
                'description' => 'Vous souhaitez présenter votre métier aux élèves ? Choisissez un créneau.',
                'slots'       => [['Matinée', 8], ['Après-midi', 8]],
            ],
        ],
        'vide' => [
            'label' => 'Portail vide (sans contenu d\'exemple)',
            'intro' => '',
        ],
    ];

    public static function valid(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    /** @return array<string,string> identifiant => libellé */
    public static function labels(): array
    {
        return array_map(static fn(array $t): string => $t['label'], self::ALL);
    }

    /** Remplit une instance neuve (à appeler dans la transaction de création). */
    public static function apply(\PDO $db, string $key): void
    {
        $t = self::ALL[$key];
        $repo = new Repo($db);
        if (!isset($t['consultation'])) {
            return;
        }
        $repo->savePost(null, ['title' => self::WELCOME['title'], 'body' => self::WELCOME['body'], 'event_at' => null, 'location' => '', 'pinned' => true]);

        // Créées dans l'ordre inverse de l'affichage (le plus récent d'abord) : la consultation reste en tête.
        $signup = $repo->createActivity('signup', $t['signup']['title'], $t['signup']['description']);
        $slots = new SignupRepo($db);
        foreach ($t['signup']['slots'] as [$label, $capacity]) {
            $slots->addSlot($signup, $label, null, $capacity);
        }

        $poll = $repo->createActivity('poll', $t['poll']['title'], $t['poll']['description']);
        $repo->updateActivity($poll, ['multiple' => 1, 'results' => 'live']);
        $options = new PollRepo($db);
        foreach ($t['poll']['options'] as $label) {
            $options->addOption($poll, $label);
        }

        $c = $t['consultation'];
        $consultation = $repo->createActivity('consultation', $c['title'], $c['description'], true);
        foreach ($c['themes'] as [$name, $color]) {
            $repo->addTheme($consultation, $name, $color);
        }
    }
}

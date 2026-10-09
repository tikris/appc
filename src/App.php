<?php
declare(strict_types=1);

namespace Appc;

/**
 * Routeur :
 *   /                          page d'accueil
 *   /_super                    console super-administrateur (création des instances)
 *   /<slug>/                   portail (accès par code)
 *   /<slug>/acces              saisie du code
 *   /<slug>/quitter            oublier ce navigateur (POST)
 *   /<slug>/admin              espace administrateur de l'établissement
 *   /<slug>/admin/export.csv   export des données
 */
final class App
{
    public static function run(): void
    {
        try {
            self::dispatch();
        } catch (HttpError $e) {
            self::fail($e->status, $e->getMessage());
        } catch (ConfigError $e) {
            self::fail(500, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[appc] ' . $e);
            self::fail(500, Config::debug() ? $e->getMessage() : 'Une erreur interne est survenue.');
        }
    }

    private static function dispatch(): void
    {
        $seg = Http::segments();

        if ($seg === []) {
            render('landing', ['pageTitle' => 'Parents à Paris Centre', 'heading' => 'Parents à Paris Centre']);
        }

        if ($seg[0] === '_super') {
            Http::$cookiePath = Config::basePath() . '/_super';
            SuperAdmin::handle(array_slice($seg, 1));
        }

        $in = Instance::load($seg[0]) ?? abort(404, 'Ce portail n\'existe pas.');
        Http::$cookiePath = Config::basePath() . '/' . $in->slug;
        $rest = array_slice($seg, 1);

        if ($rest === []) {
            if (!str_ends_with(Http::rawPath(), '/')) {
                Http::redirect(url($in->slug . '/'), 301);
            }
            (new Portal($in))->index();
        }

        $portal = new Portal($in);
        $admin = new AdminPanel($in);
        match (true) {
            $rest === ['acces']                    => $portal->access(),
            $rest === ['quitter']                  => $portal->leave(),
            $rest === ['admin']                    => $admin->handle(),
            $rest === ['admin', 'export.csv']      => $admin->export(),
            default                                => abort(404, 'Page introuvable.'),
        };
    }

    private static function fail(int $status, string $message): never
    {
        $titles = [400 => 'Requête invalide', 403 => 'Accès refusé', 404 => 'Page introuvable', 422 => 'Données invalides', 429 => 'Trop de requêtes'];
        render('error', [
            'pageTitle' => $titles[$status] ?? 'Erreur',
            'heading'   => 'Parents à Paris Centre',
            'errorCode' => $status,
            'message'   => $message,
        ], $status);
    }
}

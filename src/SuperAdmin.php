<?php
declare(strict_types=1);

namespace Appc;

/**
 * Console /_super : création des instances (un établissement = une instance, préremplie selon un modèle)
 * et réinitialisation du mot de passe de leur administrateur. Désactivée tant que super_password_hash est vide.
 */
final class SuperAdmin
{
    public static function handle(array $rest): never
    {
        $hash = (string) Config::get('super_password_hash', '');
        if ($rest !== [] || $hash === '') {
            abort(404, 'Page introuvable.');
        }
        if (!Access::isSuper()) {
            self::login($hash);
        }

        $secrets = null;
        $error = null;
        $status = 200;
        if (Http::method() === 'POST') {
            Csrf::check();
            try {
                $secrets = self::post();
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
                $status = 422;
            }
        }
        render('super', [
            'pageTitle' => 'Console super-administrateur',
            'heading'   => 'Console APPC',
            'instances' => self::overview(),
            'secrets'   => $secrets,
            'error'     => $error,
        ], $status);
    }

    private static function login(string $hash): never
    {
        $error = null;
        $status = 200;
        if (Http::method() === 'POST') {
            Csrf::check();
            $limiter = new Limiter(Db::system(), 'super');
            if ($limiter->blocked()) {
                $error = Access::MSG_BLOCKED;
                $status = 429;
            } elseif (password_verify((string) ($_POST['password'] ?? ''), $hash)) {
                $limiter->clear();
                Access::startSuper();
                Http::redirect(url('_super'));
            } else {
                $limiter->fail();
                $error = 'Mot de passe incorrect.';
                $status = 401;
            }
        }
        render('super_login', ['pageTitle' => 'Console super-administrateur', 'heading' => 'Console APPC', 'error' => $error], $status);
    }

    /** @return array{kind:string,slug:string,password:string}|null */
    private static function post(): ?array
    {
        switch ((string) ($_POST['action'] ?? '')) {
            case 'logout':
                Access::endSuper();
                Http::redirect(url('_super'));

            case 'create':
                $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
                $password = Access::generatePassword();
                Instance::create($slug, (string) ($_POST['name'] ?? ''), $password, (string) ($_POST['template'] ?? Templates::DEFAULT));
                return ['kind' => 'created', 'slug' => $slug, 'password' => $password];

            case 'reset_password':
                $slug = (string) ($_POST['slug'] ?? '');
                $in = Instance::load($slug) ?? throw new \InvalidArgumentException('Instance introuvable.');
                $password = Access::generatePassword();
                $in->set('admin_hash', password_hash($password, PASSWORD_DEFAULT));
                $in->set('must_change_pw', '1');
                Access::endAllAdminSessions($in);
                return ['kind' => 'reset', 'slug' => $slug, 'password' => $password];
        }
        throw new \InvalidArgumentException('Action inconnue.');
    }

    /** @return list<array{slug:string,name:string,stats:array,created:?int}> */
    private static function overview(): array
    {
        $rows = [];
        foreach (Instance::allSlugs() as $slug) {
            $in = Instance::load($slug);
            if ($in === null) {
                continue;
            }
            $created = $in->setting('created_at');
            $rows[] = [
                'slug'    => $slug,
                'name'    => $in->name(),
                'stats'   => (new Repo($in->db))->stats(),
                'created' => $created !== null ? (int) $created : null,
            ];
        }
        return $rows;
    }
}

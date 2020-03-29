<?php

namespace App;

use App\Model\User;
use App\Model\UserToken;
use Firebase\JWT\JWT;
use Illuminate\Events\Dispatcher;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Dotenv\Dotenv;

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(dirname(__DIR__)));
}

/**
 * Class Container
 *
 * @property Request $request
 * @property Response $response
 * @property-read \Fenom $view
 * @property-read \Monolog\Logger logger
 * @property-read \Tuupola\Middleware\JwtAuthentication jwt
 * @property-read \Illuminate\Database\Capsule\Manager capsule
 * @property-read \Illuminate\Database\DatabaseManager db
 * @property-read \App\Service\Mail $mail
 * @property-read \Vimeo\Vimeo $vimeo
 */
class Container extends \Slim\Container
{

    /** @var User $user */
    public $user = null;


    /**
     * Container constructor.
     */
    function __construct()
    {
        parent::__construct();

        try {
            $dotenv = new Dotenv(true);
            $dotenv->load(BASE_DIR . '/core/' . (get_current_user() == 's4000' ? '.prod' : '.dev') . '.env');
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        $this['view'] = function () {
            $fenom = new \Fenom(new \Fenom\Provider(BASE_DIR . '/core/templates/'));
            $fenom->setCompileDir(BASE_DIR . '/tmp/');
            $fenom->setOptions([
                'disable_native_funcs' => true,
                'disable_cache' => false,
                'force_compile' => false,
                'auto_reload' => true,
                'auto_escape' => true,
                'force_verify' => true,
            ]);

            return $fenom;
        };

        $this['capsule'] = function () {
            $capsule = new \Illuminate\Database\Capsule\Manager;
            $capsule->addConnection([
                'driver' => getenv('DB_DRIVER'),
                'host' => getenv('DB_HOST'),
                'port' => getenv('DB_PORT'),
                'prefix' => getenv('DB_PREFIX'),
                'database' => getenv('DB_DATABASE'),
                'username' => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'charset' => getenv('DB_CHARSET'),
                'collation' => getenv('DB_COLLATION'),
            ]);
            $capsule->setEventDispatcher(new Dispatcher());
            $capsule->setAsGlobal();

            return $capsule;
        };
        $this->capsule->bootEloquent();

        $this['db'] = function () {
            return $this->capsule->getDatabaseManager();
        };

        $this['logger'] = function () {
            $logger = new \Monolog\Logger('logger');
            if (PHP_SAPI == 'cli') {
                $handler = new \Monolog\Handler\EchoHandler(\Monolog\Logger::INFO);
                $handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, false, true));
            } else {
                $handler = new Service\Logger(\Monolog\Logger::ERROR);
            }
            $logger->pushHandler($handler);

            return $logger;
        };

        /*$this['jwt'] = function () {
            $container = $this;

            $jwt = new \Tuupola\Middleware\JwtAuthentication([
                'rules' => [
                    new \Tuupola\Middleware\JwtAuthentication\RequestMethodRule([
                        'ignore' => ['OPTIONS'],
                    ]),
                    new Service\Jwt([
                        'path' => '/api',
                        'force' => ['/api/web/course/lessons', '/api/web/course/comments'],
                        'ignore' => [
                            '/api/security/',
                            '/api/web/',
                        ],
                    ]),
                ],
                'cookie' => 'auth._token.local',
                'secure' => false, // Dev
                //'logger' => $this->logger,
                'secret' => getenv('JWT_SECRET'),
                'error' => function () use ($container) {
                    return (new Processor($container))->failure('Требуется авторизация', 401);
                },
                'before' => function (Request $request) use ($container) {
                    $container->user = User::query()->where([
                        'id' => $request->getAttribute('token')['id'],
                        'active' => true,
                    ])->first();
                },
                'after' => function (Response $response) use ($container) {
                    return !$container->user
                        ? (new Processor($container))->failure('Требуется авторизация', 401)
                        : $response;
                },
            ]);

            return $jwt;
        };*/

        $this['vimeo'] = function () {
            return new \Vimeo\Vimeo(getenv('VIMEO_ID'), getenv('VIMEO_SECRET'), getenv('VIMEO_TOKEN'));
        };

        $this['mail'] = function () {
            return new \App\Service\Mail($this);
        };
    }


    /**
     * @param $id
     *
     * @return string
     */
    public function makeToken($id)
    {
        $time = time();

        // Invalidate old tokens
        UserToken::query()
            ->where(['user_id' => $id, 'active' => true])
            ->where('valid_till', '<', date('Y-m-d H:i:s', $time))
            ->update(['active' => false]);

        /** @var UserToken $user_token */
        if ($user_token = UserToken::query()->where(['user_id' => $id, 'created_at' => date('Y-m-d H:i:s', $time), 'active' => true])->first()) {
            $token = $user_token->token;
        } else {
            $data = [
                'id' => $id,
                'iat' => $time,
                'exp' => $time + getenv('JWT_EXPIRE'),
            ];
            $token = JWT::encode($data, getenv('JWT_SECRET'));

            $user_token = new UserToken([
                'user_id' => $id,
                'token' => $token,
                'valid_till' => date('Y-m-d H:i:s', $data['exp']),
                'ip' => $this->request->getAttribute('ip_address'),
            ]);
            $user_token->save();
        }

        // Limit active tokens
        if (UserToken::query()->where(['user_id' => $id, 'active' => true])->count() > getenv('JWT_MAX')) {
            UserToken::query()
                ->where(['user_id' => $id, 'active' => true])
                ->orderBy('updated_at', 'asc')
                ->orderBy('created_at', 'asc')
                ->first()
                ->update(['active' => false]);
        }

        return $token;
    }


    /**
     * Check token and load user
     */
    public function loadUser()
    {
        if ($token = $this->getToken($this->request)) {
            /** @var UserToken $user_token */
            if ($user_token = UserToken::query()->where(['user_id' => $token->id, 'token' => $token->token, 'active' => true])->first()) {
                if ($user_token->valid_till > date('Y-m-d H:i:s')) {
                    /** @var User $user */
                    if ($user = $user_token->user()->where(['active' => true])->first()) {
                        $this->user = $user;

                        return true;
                    }
                } else {
                    $user_token->active = false;
                    $user_token->save();
                }
            }
        }

        return false;
    }


    /**
     * @param Request $request
     *
     * @return object|null
     */
    protected function getToken($request)
    {
        $pcre = '#Bearer\s+(.*)$#i';
        $token = null;

        $header = $request->getHeaderLine('Authorization');
        if (!empty($header) && preg_match($pcre, $header, $matches)) {
            $token = $matches[1];
        } else {
            $cookies = $request->getCookieParams();
            if (isset($cookies['auth._token.local'])) {
                $token = preg_match($pcre, $cookies['auth._token.local'], $matches)
                    ? $matches[1]
                    : $cookies['auth._token.local'];
            };
        }

        if ($token) {
            try {
                $decoded = JWT::decode($token, getenv('JWT_SECRET'), ['HS256', 'HS512', 'HS384']);
                $decoded->token = $token;
                $this->request = $this->request->withAttribute('token', $token);

                return $decoded;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

}

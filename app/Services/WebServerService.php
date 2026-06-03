<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WebServerService
{
    const WEB_SERVER_CONF = '/usr/local/openpanel/.conf/web_server.conf';
    const WEB_SERVERS_CONF = '/usr/local/openpanel/.conf/web_servers.conf';
    const VHOSTS_BASE = '/usr/local/openpanel/htdocs/resources/conf/web_servers/vhosts';
    const CONF_TEMPLATES_DIR = '/usr/local/openpanel/.conf/web_servers/conf_templates';
    const HTTPD_CONF = '/etc/httpd/conf/httpd.conf';
    const NGINX_CONF = '/etc/nginx/nginx.conf';
    const NGINX_VHOST_DIR = '/etc/nginx/conf.d/vhosts/';
    const HTTPD_VHOST_DIR = '/usr/local/apache/conf.d/vhosts/';
    const VARNISH_CONF = '/etc/varnish/varnish.params';
    const VARNISH_SITES_VCL = '/etc/varnish/sites.vcl';
    const VARNISH_DEFAULT_VCL = '/etc/varnish/default.vcl';
    const NODEJS_DIR = '/usr/local/openpanel/.conf/nodejs_apps/';
    const TOMCAT_CONF = '/usr/local/apache-tomcat/conf/server.xml';
    const TOMCAT_USERS = '/usr/local/apache-tomcat/conf/tomcat-users.xml';
    const VHOSTS_SSL_JSON = '/usr/local/openpanel/.conf/vhosts-ssl.json';

    public static function webServerConfiguration(): array
    {
        $confValue = '1';
        if (file_exists(self::WEB_SERVER_CONF)) {
            $confValue = trim(file_get_contents(self::WEB_SERVER_CONF));
        }

        switch ($confValue) {
            case '2':
                return [
                    'webserver_settings' => '2',
                    'apache_port_nonssl' => '8181',
                    'apache_port_ssl' => '8443',
                    'nginx_port_nonssl' => '80',
                    'nginx_port_ssl' => '443',
                    'nginx_proxy_port' => '8181',
                    'varnish_port' => '',
                ];
            case '4':
                return [
                    'webserver_settings' => '4',
                    'apache_port_nonssl' => '8181',
                    'apache_port_ssl' => '8443',
                    'nginx_port_nonssl' => '80',
                    'nginx_port_ssl' => '443',
                    'nginx_proxy_port' => '82',
                    'varnish_port' => '82',
                ];
            default:
                return [
                    'webserver_settings' => '1',
                    'apache_port_nonssl' => '80',
                    'apache_port_ssl' => '443',
                    'nginx_port_nonssl' => '',
                    'nginx_port_ssl' => '',
                    'nginx_proxy_port' => '',
                    'varnish_port' => '',
                ];
        }
    }

    public static function getConfigurationTemplate(array $serverArray): string
    {
        $basePath = self::VHOSTS_BASE;
        $webserver = $serverArray['webserver'];
        $extension = $serverArray['template-extension'] ?? 'tpl';

        if (isset($serverArray['template-type'])) {
            $customPath = self::CONF_TEMPLATES_DIR . '/' . $webserver . '/' . $serverArray['template-type'];
            if (is_dir($customPath)) {
                if (isset($serverArray['template-name']) && file_exists($customPath . '/' . $serverArray['template-name'] . '.' . $extension)) {
                    return $customPath . '/' . $serverArray['template-name'] . '.' . $extension;
                }
                if (file_exists($customPath . '/default.' . $extension)) {
                    return $customPath . '/default.' . $extension;
                }
            }

            $typePath = $basePath . '/' . $webserver . '/' . $serverArray['template-type'];
            if (is_dir($typePath)) {
                if (isset($serverArray['template-name']) && file_exists($typePath . '/' . $serverArray['template-name'] . '.' . $extension)) {
                    return $typePath . '/' . $serverArray['template-name'] . '.' . $extension;
                }
                if (file_exists($typePath . '/default.' . $extension)) {
                    return $typePath . '/default.' . $extension;
                }
            }
        }

        if (isset($serverArray['template-name']) && file_exists($basePath . '/' . $webserver . '/' . $serverArray['template-name'] . '.' . $extension)) {
            return $basePath . '/' . $webserver . '/' . $serverArray['template-name'] . '.' . $extension;
        }

        return $basePath . '/' . $webserver . '/default.' . $extension;
    }

    public static function checkForApacheErrors(): ?string
    {
        $exitCode = trim(ShellService::exec('/usr/local/apache/bin/apachectl -t; echo $?'));
        if ($exitCode !== '0') {
            $error = ShellService::exec('/usr/local/apache/bin/apachectl -t 2>&1');
            return '<div class="alert alert-danger"><strong>Apache ERROR Detected!</strong> ' . $error . '</div>';
        }
        return null;
    }

    public static function checkForNginxErrors(): ?string
    {
        $exitCode = trim(ShellService::exec('/usr/sbin/nginx -t; echo $?'));
        if ($exitCode !== '0') {
            $error = ShellService::exec('/usr/sbin/nginx -t 2>&1');
            return '<div class="alert alert-danger"><strong>Nginx ERROR Detected!</strong> ' . $error . '</div>';
        }
        return null;
    }

    public static function removeNginxVhost(string $action, string $domain): void
    {
        if (file_exists('/etc/nginx/conf.d/' . $domain . '.conf')) {
            ShellService::exec('test -h /etc/nginx/conf.d/' . $domain . '.conf || rm -Rf /etc/nginx/conf.d/' . $domain . '.conf');
        }
        if (!empty($action)) {
            ServerService::manageServices($action, ['nginx']);
        }
    }

    public static function removeVarnishVhost(string $action, string $domain): void
    {
        if (file_exists(self::VARNISH_SITES_VCL)) {
            ShellService::exec('sed -i \'/"' . $domain . '$"/d\' ' . self::VARNISH_SITES_VCL);
        }
        if (!empty($action)) {
            ServerService::manageServices($action, ['varnish']);
        }
    }

    public static function setWebServer(string $server): bool
    {
        $value = match ($server) {
            'nginx' => '2',
            'nginx_apache' => '2',
            'varnish' => '4',
            'litespeed' => '5',
            default => '1',
        };
        ShellService::writeFile(self::WEB_SERVER_CONF, $value);
        return true;
    }

    public static function getActiveWebServer(): string
    {
        $conf = ShellService::readFile(self::WEB_SERVER_CONF);
        $conf = trim($conf);
        return match ($conf) {
            '2' => 'nginx',
            '3' => 'nginx',
            '4' => 'varnish',
            '5' => 'litespeed',
            default => 'apache',
        };
    }

    public static function getMainConf(?string $server = null): string
    {
        $server = $server ?? self::getActiveWebServer();
        return match ($server) {
            'nginx' => ShellService::readFile('/etc/nginx/nginx.conf'),
            'litespeed' => ShellService::readFile('/usr/local/lsws/conf/httpd.conf'),
            default => ShellService::readFile(self::HTTPD_CONF),
        };
    }

    public static function saveMainConf(string $server, string $content): bool
    {
        $path = match ($server) {
            'nginx' => '/etc/nginx/nginx.conf',
            'litespeed' => '/usr/local/lsws/conf/httpd.conf',
            default => self::HTTPD_CONF,
        };
        ShellService::writeFile($path, $content);
        ServerService::manageServices('restart', [$server === 'nginx' ? 'nginx' : 'httpd']);
        return true;
    }

    public static function getDomainConf(string $domain, ?string $server = null): string
    {
        $server = $server ?? self::getActiveWebServer();
        return match ($server) {
            'nginx' => ShellService::readFile('/etc/nginx/conf.d/vhosts/' . $domain . '.conf'),
            'litespeed' => ShellService::readFile('/usr/local/lsws/conf/vhosts/' . $domain . '/vhost.conf'),
            default => ShellService::readFile('/usr/local/apache/conf.d/vhosts/' . $domain . '.conf'),
        };
    }

    public static function saveDomainConf(string $domain, string $server, string $content): bool
    {
        $path = match ($server) {
            'nginx' => '/etc/nginx/conf.d/vhosts/' . $domain . '.conf',
            'litespeed' => '/usr/local/lsws/conf/vhosts/' . $domain . '/vhost.conf',
            default => '/usr/local/apache/conf.d/vhosts/' . $domain . '.conf',
        };
        ShellService::writeFile($path, $content);
        ServerService::manageServices('restart', [$server === 'nginx' ? 'nginx' : 'httpd']);
        return true;
    }

    public static function webServersRebuild(array $arrayData): mixed
    {
        $globalConf = [];
        if (file_exists(self::WEB_SERVERS_CONF)) {
            $globalConf = json_decode(file_get_contents(self::WEB_SERVERS_CONF), true);
        }

        $homeDir = '/home/';

        if (isset($arrayData['invalid_user_conf'])) {
            $userConf = $globalConf;
            unset($userConf['varnish']);
            if (($globalConf['apache_template-type-default'] ?? '') == 'php-fpm') {
                unset($userConf['php-cgi']);
            }
        } else {
            $userConf = '';
            $userConfPath = $homeDir . $arrayData['username'] . '/.conf/webservers/' . $arrayData['domain'] . '.conf';
            if (file_exists($userConfPath)) {
                $userConf = json_decode(file_get_contents($userConfPath), true);
                if (!is_array($userConf)) {
                    $userConf = $globalConf;
                    unset($userConf['varnish']);
                    if (($globalConf['apache_template-type-default'] ?? '') == 'php-fpm') {
                        unset($userConf['php-cgi']);
                    }
                }
            } else {
                $userConf = $globalConf;
                unset($userConf['varnish']);
                if (($globalConf['apache_template-type-default'] ?? '') == 'php-fpm') {
                    unset($userConf['php-cgi']);
                }
            }
        }

        if (!is_dir($homeDir)) {
            return false;
        }

        if (!empty($globalConf)) {
            if (isset($userConf['nginx']) && isset($globalConf['nginx'])) {
                if (isset($userConf['varnish']) && isset($globalConf['varnish'])) {
                    if (isset($userConf['apache-additional'])) {
                        if (isset($userConf['php-cgi']) && isset($globalConf['php-cgi'])) {
                            $arrayData += self::buildNginxVarnishApachePhpCgiData($globalConf, $userConf);
                            if (isset($arrayData['getconf'])) {
                                return ['webservers' => 'nginx->varnish->apache->php-cgi', 'nginx' => true, 'varnish' => true, 'apache' => true, 'php' => 'php-cgi'];
                            }
                            self::logAction($arrayData['domain'] . ' Running Nginx/Varnish/Apache/PHP-CGI');
                            self::cleanDomainConf($arrayData);
                            self::buildNginxVhost($arrayData);
                            self::buildVarnishVhost($arrayData);
                            self::buildApacheVhost($arrayData);
                        } elseif (isset($userConf['php-fpm']) && isset($globalConf['php-fpm'])) {
                            $arrayData += self::buildNginxVarnishApachePhpFpmData($globalConf, $userConf);
                            if (isset($arrayData['getconf'])) {
                                return ['webservers' => 'nginx->varnish->apache->php-fpm', 'nginx' => true, 'varnish' => true, 'apache' => true, 'php' => 'php-fpm'];
                            }
                            self::logAction($arrayData['domain'] . ' Running Nginx/Varnish/Apache/PHP-FPM');
                            self::cleanDomainConf($arrayData);
                            self::buildNginxVhost($arrayData);
                            self::buildVarnishVhost($arrayData);
                            self::buildApacheVhost($arrayData);
                            self::buildPhpFpmVhost($arrayData);
                        } else {
                            self::logAction($arrayData['domain'] . ' Running Default (no php backend)');
                            $arrayData['invalid_user_conf'] = true;
                            return self::webServersRebuild($arrayData);
                        }
                    } else {
                        $arrayData += self::buildNginxVarnishData($globalConf, $userConf);
                        if (isset($arrayData['getconf'])) {
                            return ['webservers' => 'nginx->varnish->proxy', 'nginx' => true, 'varnish' => true];
                        }
                        self::logAction($arrayData['domain'] . ' Running Nginx/Varnish/Proxy');
                        self::cleanDomainConf($arrayData);
                        self::buildNginxVhost($arrayData);
                        self::buildVarnishVhost($arrayData);
                    }
                } elseif (isset($userConf['apache-additional']) && isset($globalConf['apache-additional'])) {
                    if (isset($userConf['php-fpm']) && isset($globalConf['php-fpm'])) {
                        $arrayData += self::buildNginxApachePhpFpmData($globalConf, $userConf);
                        if (isset($arrayData['getconf'])) {
                            return ['webservers' => 'nginx->apache->php-fpm', 'nginx' => true, 'apache' => true, 'php' => 'php-fpm'];
                        }
                        self::logAction($arrayData['domain'] . ' Running Nginx/Apache/PHP-FPM');
                        self::cleanDomainConf($arrayData);
                        self::buildNginxVhost($arrayData);
                        self::buildApacheVhost($arrayData);
                        self::buildPhpFpmVhost($arrayData);
                    } elseif (isset($userConf['php-cgi']) && isset($globalConf['php-cgi'])) {
                        $arrayData += self::buildNginxApachePhpCgiData($globalConf, $userConf);
                        if (isset($arrayData['getconf'])) {
                            return ['webservers' => 'nginx->apache->php-cgi', 'nginx' => true, 'apache' => true, 'php' => 'php-cgi'];
                        }
                        self::logAction($arrayData['domain'] . ' Running Nginx/Apache/PHP-CGI');
                        self::cleanDomainConf($arrayData);
                        self::buildNginxVhost($arrayData);
                        self::buildApacheVhost($arrayData);
                    } else {
                        self::logAction($arrayData['domain'] . ' Running Default (no php backend)');
                        $arrayData['invalid_user_conf'] = true;
                        return self::webServersRebuild($arrayData);
                    }
                } elseif (isset($userConf['php-fpm']) && isset($globalConf['php-fpm'])) {
                    $arrayData += self::buildNginxPhpFpmData($globalConf, $userConf);
                    if (isset($arrayData['getconf'])) {
                        return ['webservers' => 'nginx->phpfpm', 'nginx' => true, 'php' => 'php-fpm'];
                    }
                    self::logAction($arrayData['domain'] . ' Running Nginx/PHP-FPM');
                    self::cleanDomainConf($arrayData);
                    self::buildNginxVhost($arrayData);
                    self::buildPhpFpmVhost($arrayData);
                } else {
                    self::logAction($arrayData['domain'] . ' Running Default (no backend)');
                    $arrayData['invalid_user_conf'] = true;
                    return self::webServersRebuild($arrayData);
                }
            } elseif (isset($userConf['litespeed']) && isset($globalConf['litespeed'])) {
                $arrayData += [
                    'apache_port' => '80',
                    'apache_port-ssl' => '443',
                    'apache_template-type-default' => $globalConf['apache_template-type-default'] ?? 'php-cgi',
                    'apache_template-name-default' => $globalConf['apache_template-name-default'] ?? 'default',
                ];
                if (!empty($userConf)) {
                    $arrayData += $userConf;
                }
                self::logAction($arrayData['domain'] . ' Running litespeed');
                self::cleanDomainConf($arrayData);
                self::buildApacheVhost($arrayData);
            } elseif (isset($userConf['apache-main']) && isset($globalConf['apache-main'])) {
                if (isset($userConf['php-fpm']) && isset($globalConf['php-fpm'])) {
                    $arrayData += self::buildApachePhpFpmData($globalConf);
                    $arrayData['apache_access-logs'] = true;
                    if (!empty($userConf)) {
                        $arrayData += $userConf;
                    }
                    if (isset($arrayData['getconf'])) {
                        return ['webservers' => 'apache->php-fpm', 'apache' => true, 'php' => 'php-fpm'];
                    }
                    self::logAction($arrayData['domain'] . ' Running Apache/PHP-FPM');
                    self::cleanDomainConf($arrayData);
                    self::buildApacheVhost($arrayData);
                    self::buildPhpFpmVhost($arrayData);
                } elseif (isset($userConf['php-cgi']) && isset($globalConf['php-cgi'])) {
                    $arrayData += self::buildApachePhpCgiData($globalConf);
                    $arrayData['apache_access-logs'] = true;
                    if (!empty($userConf)) {
                        $arrayData += $userConf;
                    }
                    if (isset($arrayData['getconf'])) {
                        return ['webservers' => 'apache->php-cgi', 'apache' => true, 'php' => 'php-cgi'];
                    }
                    self::logAction($arrayData['domain'] . ' Running Apache/PHP-CGI');
                    self::cleanDomainConf($arrayData);
                    self::buildApacheVhost($arrayData);
                } else {
                    self::logAction($arrayData['domain'] . ' Running Default (no php)');
                    $arrayData['invalid_user_conf'] = true;
                    return self::webServersRebuild($arrayData);
                }
            } else {
                self::logAction($arrayData['domain'] . ' Running Default (no matching config)');
                $arrayData['invalid_user_conf'] = true;
                return self::webServersRebuild($arrayData);
            }
        }

        return true;
    }

    protected static function buildNginxVarnishApachePhpCgiData(array $global, array $user): array
    {
        $data = [
            'apache_port' => '8181', 'apache_port-ssl' => '8443',
            'apache_template-type-default' => $global['apache_template-type-default'] ?? 'php-cgi',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
            'nginx_port' => '80', 'nginx_port-ssl' => '443', 'nginx_proxyto-port-default' => '82',
            'nginx_template-type-default' => $global['nginx_template-type-default'] ?? 'proxy',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
            'varnish_port' => '82', 'varnish_proxyto-port-default' => '8181',
            'varnish_template-name-default' => $global['varnish_template-name-default'] ?? 'default',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildNginxVarnishApachePhpFpmData(array $global, array $user): array
    {
        $data = [
            'apache_port' => '8181', 'apache_port-ssl' => '8443',
            'apache_template-type-default' => 'php-fpm',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
            'apache_template-backend-fcgi' => $global['apache_template-backend-fcgi'] ?? 'socket',
            'apache-php-fpm_ver-default' => $global['apache-php-fpm_ver-default'] ?? '',
            'php-fpm_template-name-default' => $global['php-fpm_template-name-default'] ?? 'default',
            'nginx_port' => '80', 'nginx_port-ssl' => '443',
            'nginx_template-type-default' => $global['nginx_template-type-default'] ?? 'proxy',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
            'nginx_proxyto-port-default' => '82',
            'varnish_port' => '82', 'varnish_proxyto-port-default' => '8181',
            'varnish_template-default' => $global['varnish_template-default'] ?? 'default',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildNginxVarnishData(array $global, array $user): array
    {
        $data = [
            'nginx_port' => '80', 'nginx_port-ssl' => '443', 'nginx_proxyto-port-default' => '82',
            'nginx_template-type-default' => $global['nginx_template-type-default'] ?? 'proxy',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
            'varnish_port' => '82', 'varnish_proxyto-port-default' => '8181',
            'varnish_template-name-default' => $global['varnish_template-name-default'] ?? 'default',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildNginxApachePhpFpmData(array $global, array $user): array
    {
        $data = [
            'apache_port' => '8181', 'apache_port-ssl' => '8443',
            'apache_template-type-default' => 'php-fpm',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
            'apache_template-backend-fcgi' => $global['apache_template-backend-fcgi'] ?? 'socket',
            'apache-php-fpm_ver-default' => $global['apache-php-fpm_ver-default'] ?? '',
            'php-fpm_template-name-default' => $global['php-fpm_template-name-default'] ?? 'default',
            'nginx_port' => '80', 'nginx_port-ssl' => '443',
            'nginx_template-type-default' => $global['nginx_template-type-default'] ?? 'proxy',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
            'nginx_proxyto-port-default' => '8181',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildNginxApachePhpCgiData(array $global, array $user): array
    {
        $data = [
            'apache_port' => '8181', 'apache_port-ssl' => '8443',
            'apache_template-type-default' => $global['apache_template-type-default'] ?? 'php-cgi',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
            'nginx_port' => '80', 'nginx_port-ssl' => '443', 'nginx_proxyto-port-default' => '8181',
            'nginx_template-type-default' => $global['nginx_template-type-default'] ?? 'proxy',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildNginxPhpFpmData(array $global, array $user): array
    {
        $data = [
            'php-fpm_template-name-default' => $global['php-fpm_template-name-default'] ?? 'default',
            'nginx_port' => '80', 'nginx_port-ssl' => '443',
            'nginx_template-type-default' => 'php-fpm',
            'nginx-php-fpm_ver-default' => $global['nginx-php-fpm_ver-default'] ?? '',
            'nginx_template-name-default' => $global['nginx_template-name-default'] ?? 'default',
            'nginx_template-backend-fcgi' => 'socket',
            'nginx_template-proxy-extensions' => $global['nginx_template-proxy-extensions'] ?? 'jpeg|jpg|png|gif|bmp|ico|svg|css|js',
            'nginx_template-proxy-extensions-default' => $global['nginx_template-proxy-extensions'] ?? 'jpeg|jpg|png|gif|bmp|ico|svg|css|js',
        ];
        if (!empty($user)) $data += $user;
        return $data;
    }

    protected static function buildApachePhpFpmData(array $global): array
    {
        return [
            'apache_port' => '80', 'apache_port-ssl' => '443',
            'apache_template-type-default' => 'php-fpm',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
            'apache_template-backend-fcgi' => $global['apache_template-backend-fcgi'] ?? 'socket',
            'apache-php-fpm_ver-default' => $global['apache-php-fpm_ver-default'] ?? '',
            'php-fpm_template-name-default' => $global['php-fpm_template-name-default'] ?? 'default',
        ];
    }

    protected static function buildApachePhpCgiData(array $global): array
    {
        return [
            'apache_port' => '80', 'apache_port-ssl' => '443',
            'apache_template-type-default' => $global['apache_template-type-default'] ?? 'php-cgi',
            'apache_template-name-default' => $global['apache_template-name-default'] ?? 'default',
        ];
    }

    protected static function cleanDomainConf(array $data): void
    {
        $domain = $data['domain'];

        $paths = [
            '/usr/local/apache/conf.d/vhosts/' . $domain . '.conf',
            '/usr/local/apache/conf.d/vhosts/' . $domain . '.ssl.conf',
            '/usr/local/apache/conf.d/vhosts/' . $domain . '.conf.disabled',
            '/usr/local/apache/conf.d/vhosts/' . $domain . '.ssl.conf.disabled',
            '/etc/nginx/conf.d/vhosts/' . $domain . '.conf',
            '/etc/nginx/conf.d/vhosts/' . $domain . '.ssl.conf',
            '/etc/nginx/conf.d/vhosts/' . $domain . '.conf.disabled',
            '/etc/nginx/conf.d/vhosts/' . $domain . '.ssl.conf.disabled',
            '/etc/varnish/conf.d/vhosts/' . $domain . '.conf',
            '/etc/varnish/conf.d/vhosts/' . $domain . '.conf.disabled',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                ShellService::exec('rm -f ' . escapeshellarg($path));
            }
        }

        if (file_exists('/etc/varnish/conf.d/vhosts/' . $domain . '.conf')) {
            ShellService::exec('echo "vcl 4.0;" > /etc/varnish/conf.d/vhosts.conf');
            ShellService::exec('ls /etc/varnish/conf.d/vhosts/*.conf 2> /dev/null | awk \'{ printf "include \\"%s\\";\\n", $1 }\' >> /etc/varnish/conf.d/vhosts.conf');
        }

        self::logAction($domain . ' Delete all configuration for domain');
    }

    protected static function buildApacheVhost(array $data): void
    {
        $required = ['ip-address', 'email', 'path', 'username', 'domain', 'apache_port'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                self::logAction($data['domain'] . ' buildApacheVhost Missing: ' . $key);
                return;
            }
        }

        $tplArray = ['webserver' => 'httpd', 'template-extension' => 'tpl'];
        if (isset($data['apache_template-name'])) {
            $tplArray['template-type'] = $data['apache_template-type'] ?? $data['apache_template-type-default'];
            $tplArray['template-name'] = $data['apache_template-name'];
        } else {
            $tplArray['template-type'] = $data['apache_template-type-default'];
            $tplArray['template-name'] = $data['apache_template-name-default'];
        }

        if (($tplArray['template-type'] ?? '') == 'php-fpm' && !isset($data['apache_template-backend-fcgi'])) {
            $data['apache_template-backend-fcgi'] = 'socket';
        }

        if (file_exists('/usr/local/openpanel/users/suspended/' . $data['username'])) {
            $tplArray['template-name'] = 'suspend';
        }
        if (file_exists('/usr/local/openpanel/users/suspended/' . $data['username'] . '.bandwidth')) {
            $tplArray['template-name'] = 'suspend';
        }

        $data['domain-aliases'] = 'ServerAlias www.' . $data['domain'];

        $ipAddress = $data['ip-address'];
        if (self::natIp($ipAddress) !== false) {
            $ipAddress = self::natIp($ipAddress);
        }

        $tplPath = self::getConfigurationTemplate($tplArray);
        if (!file_exists($tplPath)) return;

        $vhostContent = file_get_contents($tplPath);
        $vhostContent = str_replace('%ip%', trim($ipAddress), $vhostContent);
        $vhostContent = str_replace('%apache_port%', $data['apache_port'], $vhostContent);
        $vhostContent = str_replace('%domain_idn%', $data['domain'], $vhostContent);
        $vhostContent = str_replace('%domain%', $data['domain'], $vhostContent);
        $vhostContent = str_replace('%domain_aliases%', $data['domain-aliases'], $vhostContent);
        $vhostContent = str_replace('%user%', $data['username'], $vhostContent);
        $vhostContent = str_replace('%group%', $data['username'], $vhostContent);
        $vhostContent = str_replace('%docroot%', $data['path'], $vhostContent);
        $vhostContent = str_replace('%home%', '/home', $vhostContent);

        if (!isset($data['apache_access-logs'])) {
            $vhostContent = str_replace('CustomLog', '#CustomLog', $vhostContent);
        }

        if (isset($data['apache_template-backend-fcgi']) && $data['apache_template-backend-fcgi'] == 'socket') {
            if (isset($data['php-fpm_ver'])) {
                $phpFpmPath = '/opt/alt/php-fpm' . $data['php-fpm_ver'];
                if (file_exists($phpFpmPath)) {
                    $socketPath = 'unix:' . $phpFpmPath . '/usr/var/sockets/' . $data['username'] . '.sock';
                    $vhostContent = str_replace('%backend_fcgi%', $socketPath, $vhostContent);
                } elseif (isset($data['apache-php-fpm_ver-default'])) {
                    $socketPath = 'unix:/opt/alt/php-fpm' . $data['apache-php-fpm_ver-default'] . '/usr/var/sockets/' . $data['username'] . '.sock';
                    $vhostContent = str_replace('%backend_fcgi%', $socketPath, $vhostContent);
                }
            } elseif (isset($data['apache-php-fpm_ver-default'])) {
                $socketPath = 'unix:/opt/alt/php-fpm' . $data['apache-php-fpm_ver-default'] . '/usr/var/sockets/' . $data['username'] . '.sock';
                $vhostContent = str_replace('%backend_fcgi%', $socketPath, $vhostContent);
            }
        }

        $vhostPath = '/usr/local/apache/conf.d/vhosts/' . $data['domain'] . '.conf';
        file_put_contents($vhostPath, $vhostContent, LOCK_EX);
        self::logAction($data['domain'] . ' Apache vhost created');
    }

    protected static function buildNginxVhost(array $data): void
    {
        $required = ['ip-address', 'email', 'path', 'username', 'domain', 'nginx_port'];
        foreach ($required as $key) {
            if (empty($data[$key])) {
                self::logAction($data['domain'] . ' buildNginxVhost Missing: ' . $key);
                return;
            }
        }

        $tplArray = ['webserver' => 'nginx', 'template-extension' => 'tpl'];
        if (isset($data['nginx_template-type'])) {
            $tplArray['template-type'] = $data['nginx_template-type'];
        } else {
            $tplArray['template-type'] = $data['nginx_template-type-default'] ?? '';
        }
        $tplArray['template-name'] = $data['nginx_template-name-default'] ?? 'default';

        if (isset($data['nginx_template-proxy-extensions'])) {
            $data['proxy_extensions'] = $data['nginx_template-proxy-extensions'];
        } else {
            $data['proxy_extensions'] = $data['nginx_template-proxy-extensions-default'] ?? 'jpeg|jpg|png|gif|bmp|ico|svg|css|js';
        }

        $ipAddress = $data['ip-address'];
        if (self::natIp($ipAddress) !== false) {
            $ipAddress = self::natIp($ipAddress);
        }

        $tplPath = self::getConfigurationTemplate($tplArray);
        if (!file_exists($tplPath)) return;

        $vhostContent = file_get_contents($tplPath);
        $vhostContent = str_replace('%ip%', trim($ipAddress), $vhostContent);
        $vhostContent = str_replace('%nginx_port%', $data['nginx_port'], $vhostContent);
        $vhostContent = str_replace('%domain_idn%', $data['domain'], $vhostContent);
        $vhostContent = str_replace('%domain%', $data['domain'], $vhostContent);
        $vhostContent = str_replace('%alias_idn%', '', $vhostContent);
        $vhostContent = str_replace('%docroot%', $data['path'], $vhostContent);
        $vhostContent = str_replace('%proxy_extensions%', $data['proxy_extensions'], $vhostContent);

        if (isset($data['nginx_proxyto-port-default'])) {
            $vhostContent = str_replace('%proxy_port%', $data['nginx_proxyto-port-default'], $vhostContent);
            $vhostContent = str_replace('%proxy_protocol%', 'http', $vhostContent);
            $vhostContent = str_replace('%proxy_ip%', trim($ipAddress), $vhostContent);
        }

        if (isset($data['nginx-php-fpm_ver-default'])) {
            $socketPath = 'unix:/opt/alt/php-fpm' . $data['nginx-php-fpm_ver-default'] . '/usr/var/sockets/' . $data['username'] . '.sock';
            $vhostContent = str_replace('%backend_fcgi%', $socketPath, $vhostContent);
        }

        $vhostPath = '/etc/nginx/conf.d/vhosts/' . $data['domain'] . '.conf';
        file_put_contents($vhostPath, $vhostContent, LOCK_EX);
        self::logAction($data['domain'] . ' Nginx vhost created');
    }

    protected static function buildVarnishVhost(array $data): void
    {
        $domain = $data['domain'];
        $apachePort = $data['apache_port'] ?? '8181';
        $ipAddress = $data['ip-address'] ?? '127.0.0.1';

        if (self::natIp($ipAddress) !== false) {
            $ipAddress = self::natIp($ipAddress);
        }

        $vclEntry = 'backend ' . preg_replace('/[^a-zA-Z0-9_]/', '_', $domain) . ' { .host = "' . $ipAddress . '"; .port = "' . $apachePort . '"; }';
        $sitesVcl = "/etc/varnish/conf.d/vhosts/{$domain}.conf";
        file_put_contents($sitesVcl, "vcl 4.0;\n{$vclEntry}\n", LOCK_EX);

        self::logAction($domain . ' Varnish vhost created');
    }

    protected static function buildPhpFpmVhost(array $data): void
    {
        $username = $data['username'];
        $phpFpmVer = $data['php-fpm_ver'] ?? $data['nginx-php-fpm_ver-default'] ?? $data['apache-php-fpm_ver-default'] ?? '';

        if (empty($phpFpmVer)) return;

        $fpmDir = '/opt/alt/php-fpm' . $phpFpmVer;
        if (!file_exists($fpmDir . '/usr/sbin/php-fpm')) return;

        $tplPath = $fpmDir . '/usr/etc/php-fpm.d/users/' . $username . '.conf';
        if (file_exists($tplPath)) return;

        $tplDir = $fpmDir . '/usr/etc/php-fpm.d/users/';
        if (!is_dir($tplDir)) {
            mkdir($tplDir, 0755, true);
        }

        $poolContent = "[{$username}]\n";
        $poolContent .= "user = {$username}\n";
        $poolContent .= "group = {$username}\n";
        $poolContent .= "listen = {$fpmDir}/usr/var/sockets/{$username}.sock\n";
        $poolContent .= "listen.owner = {$username}\n";
        $poolContent .= "listen.group = nobody\n";
        $poolContent .= "pm = ondemand\n";
        $poolContent .= "pm.max_children = 5\n";
        $poolContent .= "pm.process_idle_timeout = 60s\n";
        $poolContent .= "pm.max_requests = 500\n";

        file_put_contents($tplPath, $poolContent, LOCK_EX);
        self::logAction($data['domain'] . ' PHP-FPM pool created for ' . $username);
    }

    protected static function natIp(string $ip): string|false
    {
        $natConf = '/usr/local/openpanel/.conf/nat.conf';
        if (file_exists($natConf)) {
            $nat = json_decode(file_get_contents($natConf), true);
            if (isset($nat['nat']) && $nat['nat'] == 'ON' && !empty($nat['local_ip']) && !empty($nat['public_ip'])) {
                if ($ip === $nat['local_ip']) {
                    return $nat['public_ip'];
                }
            }
        }
        return false;
    }

    protected static function logAction(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = '/var/log/openpanel/webservers.log';
        ShellService::exec("echo '[{$timestamp}] {$message}' >> {$logFile}");
    }

    public static function webServersSettings(): array
    {
        $row = DB::connection('mysql')->table('settings')->select('homedir', 'apache_port', 'shared_ip')->first();
        if (!$row) return ['homedir' => '/home', 'apache_port' => '80', 'shared_ip' => '127.0.0.1'];

        $result = (array) $row;
        if (isset($result['shared_ip']) && self::natIp($result['shared_ip']) !== false) {
            $result['shared_ip'] = self::natIp($result['shared_ip']);
        }
        return $result;
    }

    public static function getInstalledPhpFpmVersions(): array
    {
        $versions = [];
        if (!is_dir('/opt/alt/')) return $versions;

        $dirs = ShellService::exec('cd /opt/alt/; ls -d php-fpm* 2>/dev/null');
        if (empty(trim($dirs))) return $versions;

        foreach (explode("\n", $dirs) as $dir) {
            $dir = trim($dir);
            if (empty($dir)) continue;
            if (file_exists('/opt/alt/' . $dir . '/usr/sbin/php-fpm')) {
                $versions[] = $dir;
            }
        }
        return $versions;
    }

    public static function getAllPhpVersionsInstalled(): array
    {
        $dirs = scandir('/opt/alt/');
        $phpFpm = [];
        $phpCgi = [];
        $fpmIdx = 0;
        $cgiIdx = 0;

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (strpos($dir, 'fpm') !== false) {
                $phpFpm[$fpmIdx] = number_format(str_replace('php-fpm', '', $dir) / 10, 1);
                $fpmIdx++;
            } else {
                $phpCgi[$cgiIdx] = number_format(str_replace('php', '', $dir) / 10, 1);
                $cgiIdx++;
            }
        }

        return ['status' => 'OK', 'resp' => ['php-fpm' => $phpFpm, 'php-cgi' => $phpCgi]];
    }

    public static function webServersPrepareCleanVhosts(array $serverArray): void
    {
        if (in_array('varnish', $serverArray)) {
            $settings = self::webServersSettings();
            ShellService::exec("echo '' > /etc/varnish/default.vcl");
            ShellService::exec('rm -f /etc/varnish/conf.d/* /etc/varnish/conf.d/vhosts/*.conf /etc/varnish/conf.d/vhosts/*.disabled');

            $sharedIp = $settings['shared_ip'];
            if (self::natIp($sharedIp) !== false) {
                $sharedIp = self::natIp($sharedIp);
            }

            ShellService::exec('echo \'vcl 4.0;\' > /etc/varnish/default.vcl');
            ShellService::exec('echo \'backend default { .host = "' . $sharedIp . '"; .port = "' . $settings['apache_port'] . '";}\' >> /etc/varnish/default.vcl');
            ShellService::exec('mkdir -p /etc/varnish/conf.d');
            ShellService::exec('echo \'include "/etc/varnish/conf.d/vhosts.conf";\' >> /etc/varnish/default.vcl');
            ShellService::exec('echo "vcl 4.0;" > /etc/varnish/conf.d/vhosts.conf');
            ShellService::exec('ls /etc/varnish/conf.d/vhosts/*.conf 2> /dev/null| awk \'{ printf "include \\"%s\\";\\n", $1 }\' >> /etc/varnish/conf.d/vhosts.conf');
        }

        if (in_array('httpd', $serverArray)) {
            $backupDir = '/usr/local/apache/openpanel_backups';
            if (!file_exists($backupDir)) {
                ShellService::exec("mkdir -p {$backupDir}");
            }
            ShellService::exec('rm -f /usr/local/apache/conf.d/vhosts.conf; rm -f /usr/local/apache/conf.d/vhosts/*.conf /usr/local/apache/conf.d/vhosts/*.disabled');
            ShellService::exec('echo "IncludeOptional /usr/local/apache/conf.d/vhosts/*.conf" > /usr/local/apache/conf.d/vhosts.conf');
            if (file_exists('/usr/local/apache/conf.d/vhosts-ssl.conf')) {
                ShellService::exec('mv /usr/local/apache/conf.d/vhosts-ssl.conf /usr/local/apache/conf.d/vhosts-ssl.conf.old');
            }
            if (file_exists('/usr/local/apache/conf.d/vhosts-ssl-letsencrypt.conf')) {
                ShellService::exec('mv /usr/local/apache/conf.d/vhosts-ssl-letsencrypt.conf /usr/local/apache/conf.d/vhosts-ssl-letsencrypt.conf.old');
            }
        }

        if (in_array('nginx', $serverArray)) {
            ShellService::exec('rm -f /etc/nginx/conf.d/* /etc/nginx/conf.d/vhosts/*.conf /etc/nginx/conf.d/vhosts/*.disabled');
        }

        if (in_array('php-fpm', $serverArray)) {
            ShellService::exec('rm -f /opt/alt/php-fpm*/usr/etc/php-fpm.d/users/*.conf');
        }
    }

    public static function rebuildAll(): string
    {
        $output = '';
        $output .= ShellService::exec('/usr/local/openpanel/htdocs/resources/admin/scripts/rebuild_httpd_conf 2>&1') . "\n";
        $output .= ServerService::manageServices('restart', ['httpd']);
        return $output;
    }

    public static function getVhostTemplates(?string $server = null): array
    {
        $server = $server ?? self::getActiveWebServer();
        $dir = self::VHOSTS_BASE . '/' . $server;
        $templates = [];
        if (!is_dir($dir)) return $templates;

        $result = ShellService::exec("cd {$dir}; ls *.tpl 2>/dev/null");
        if (!empty(trim($result))) {
            foreach (explode("\n", $result) as $file) {
                $file = trim($file);
                if (!empty($file)) {
                    $templates[] = ['name' => $file, 'content' => ShellService::readFile($dir . '/' . $file)];
                }
            }
        }
        return $templates;
    }

    public static function getVhostTemplate(string $template, ?string $server = null): string
    {
        $server = $server ?? self::getActiveWebServer();
        return ShellService::readFile(self::VHOSTS_BASE . '/' . $server . '/' . $template);
    }

    public static function saveVhostTemplate(string $template, string $content, ?string $server = null): bool
    {
        $server = $server ?? self::getActiveWebServer();
        return ShellService::writeFile(self::VHOSTS_BASE . '/' . $server . '/' . $template, $content);
    }

    public static function getApacheStatus(): string
    {
        return ShellService::exec('systemctl status httpd 2>/dev/null');
    }

    public static function apacheRebuild(): string
    {
        return ShellService::exec('/scripts/rebuild_httpd_conf 2>&1');
    }

    public static function getVarnishConfig(): string
    {
        return ShellService::readFile(self::VARNISH_CONF);
    }

    public static function saveVarnishConfig(string $content): bool
    {
        ShellService::writeFile(self::VARNISH_CONF, $content);
        ServerService::manageServices('restart', ['varnish']);
        return true;
    }

    public static function getNodeJsVersions(): array
    {
        $output = ShellService::exec('which node 2>/dev/null && node -v 2>/dev/null');
        return ['installed' => !empty(trim($output)), 'version' => trim($output)];
    }

    public static function getNodeJsApps(): array
    {
        $apps = [];
        if (!is_dir(self::NODEJS_DIR)) return $apps;
        foreach (ShellService::dirList(self::NODEJS_DIR) as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $conf = self::NODEJS_DIR . $dir . '/app.json';
            if (file_exists($conf)) {
                $config = json_decode(ShellService::readFile($conf), true);
                $apps[] = [
                    'name' => $dir,
                    'path' => self::NODEJS_DIR . $dir,
                    'config' => $config,
                    'status' => ShellService::exec("pm2 show " . escapeshellarg($dir) . " 2>/dev/null | grep status"),
                ];
            }
        }
        return $apps;
    }

    public static function nodeJsAppAction(string $action, string $app): string
    {
        return match ($action) {
            'start' => ShellService::exec("cd " . escapeshellarg(self::NODEJS_DIR . $app) . " && pm2 start app.json 2>&1"),
            'stop' => ShellService::exec("pm2 stop " . escapeshellarg($app) . " 2>&1"),
            'restart' => ShellService::exec("pm2 restart " . escapeshellarg($app) . " 2>&1"),
            'delete' => ShellService::exec("pm2 delete " . escapeshellarg($app) . " 2>&1"),
            default => 'Invalid action',
        };
    }

    public static function getTomcatConfig(): string
    {
        return ShellService::readFile(self::TOMCAT_CONF);
    }

    public static function saveTomcatConfig(string $content): bool
    {
        ShellService::writeFile(self::TOMCAT_CONF, $content);
        ServerService::manageServices('restart', ['tomcat']);
        return true;
    }

    public static function getTomcatUsers(): string
    {
        return ShellService::readFile(self::TOMCAT_USERS);
    }

    const SSL_JSON = '/usr/local/openpanel/.conf/vhosts-ssl.json';
    const ACME_SH = '/root/.acme.sh/acme.sh';
    const ACME_CERTS_DIR = '/root/.acme.sh/openpanel_certs';
    const SSL_PRIVATE_DIR = '/etc/pki/tls/private';
    const SSL_CERTS_DIR = '/etc/pki/tls/certs';
    const DOVECOT_SNI_CONF = '/etc/dovecot/sni.conf';
    const POSTFIX_VMAIL_SSL = '/etc/postfix/vmail_ssl.map';

    public static function acmeEngine(): bool
    {
        if (!file_exists(self::ACME_SH)) {
            ShellService::exec("cd /usr/local/src; rm -f acme.sh index.html; wget http://get.acme.sh; mv index.html acme.sh; sh acme.sh; /root/.acme.sh/acme.sh --set-default-ca --server letsencrypt");
            if (!file_exists(self::ACME_SH)) {
                return false;
            }
        }
        return true;
    }

    public static function autoSslIssue(string $domain, string $username, int $keySize = 2048, string $www = 'www', array $san = []): string
    {
        if (!self::acmeEngine()) {
            return 'acme.sh installation failed';
        }

        ShellService::exec("mkdir -p " . self::SSL_PRIVATE_DIR);

        $webroot = '/usr/local/apache/autossl_tmp/';
        $sanArgs = '';
        if (!empty($san)) {
            $sanArgs = implode(' ', array_map(fn($s) => '-d ' . $s . '.' . $domain, $san)) . ' ';
        }

        $certPath = self::SSL_CERTS_DIR . '/' . $domain . '.cert';
        $keyPath = self::SSL_PRIVATE_DIR . '/' . $domain . '.key';
        $bundlePath = self::SSL_CERTS_DIR . '/' . $domain . '.bundle';

        if ($www === 'www') {
            $output = ShellService::exec(self::ACME_SH . " --issue --cert-home " . self::ACME_CERTS_DIR . " -d www.{$domain} -d {$domain} {$sanArgs}-w {$webroot} --certpath {$certPath} --keypath {$keyPath} --fullchainpath {$bundlePath} --listen-v4 --force --log 2>&1");
            if (!file_exists($certPath)) {
                $output = ShellService::exec(self::ACME_SH . " --issue --cert-home " . self::ACME_CERTS_DIR . " -d {$domain} {$sanArgs}-w {$webroot} --certpath {$certPath} --keypath {$keyPath} --fullchainpath {$bundlePath} --listen-v4 --force --log 2>&1");
            }
        } else {
            $output = ShellService::exec(self::ACME_SH . " --issue --cert-home " . self::ACME_CERTS_DIR . " -d {$domain} {$sanArgs}-w {$webroot} --certpath {$certPath} --keypath {$keyPath} --fullchainpath {$bundlePath} --listen-v4 --force --log 2>&1");
        }

        if (strpos($output, 'limits') !== false || strpos($output, 'Invalid response') !== false || strpos($output, 'Verify error') !== false) {
            return $output;
        }

        if (!file_exists($bundlePath)) {
            ShellService::exec("echo ' ' > {$bundlePath}");
        }
        if (file_exists($keyPath)) {
            ShellService::exec("test -h \"{$keyPath}\" || chmod 640 {$keyPath}");
        }

        if (file_exists($certPath) && file_exists($keyPath) && file_exists($bundlePath)) {
            $certContent = file_get_contents($certPath);
            $keyContent = file_get_contents($keyPath);
            if (openssl_x509_check_private_key($certContent, $keyContent)) {
                self::storeDomainConfig($domain, $username, $san);
                return 'success';
            }
            return 'INVALID SSL FILE';
        }

        return 'AutoSSL Issue Failed! ' . $output;
    }

    public static function autoSslRenew(string $domain): string
    {
        if (!file_exists(self::ACME_SH)) {
            return 'acme.sh not installed';
        }
        $output = ShellService::exec(self::ACME_SH . " --renew -d " . escapeshellarg($domain) . " --force --log 2>&1");
        self::logAction("AutoSSL renewed: {$domain}");
        return $output;
    }

    public static function autoSslRevoke(string $domain): string
    {
        if (!file_exists(self::ACME_SH)) {
            return 'acme.sh not installed';
        }
        $output = ShellService::exec(self::ACME_SH . " --revoke -d " . escapeshellarg($domain) . " 2>&1");
        self::removeSslFiles($domain);
        self::completeRemoveAutoSsl($domain);
        return $output;
    }

    public static function hostnameAutoSsl(string $hostname, int $keySize = 2048): bool
    {
        if (!self::acmeEngine()) return false;

        ShellService::exec("mkdir -p " . self::SSL_PRIVATE_DIR);

        foreach (['hostname.key', 'hostname.crt', 'hostname.cert', 'hostname.bundle', 'hostname.pem'] as $file) {
            $dir = strpos($file, '.key') !== false || strpos($file, '.pem') !== false ? self::SSL_PRIVATE_DIR : self::SSL_CERTS_DIR;
            if (file_exists("{$dir}/{$file}")) {
                ShellService::exec("unlink {$dir}/{$file}");
            }
        }

        if (!file_exists('/usr/local/apache/htdocs/.well-known')) {
            ShellService::exec("mkdir /usr/local/apache/htdocs/.well-known; test -h '/usr/local/apache/htdocs' || chown -R nobody:nobody /usr/local/apache/htdocs");
        }

        $webroot = '/usr/local/apache/autossl_tmp/';
        $output = ShellService::exec(self::ACME_SH . " --issue --cert-home " . self::ACME_CERTS_DIR . " -d {$hostname} -w {$webroot} --certpath " . self::SSL_CERTS_DIR . "/hostname.cert --keypath " . self::SSL_PRIVATE_DIR . "/hostname.key --fullchainpath " . self::SSL_CERTS_DIR . "/hostname.bundle --keylength {$keySize} --force --renew-hook 'sh /scripts/hostname_ssl_restart_services' --log 2>&1");

        if (file_exists(self::SSL_CERTS_DIR . '/hostname.bundle') && file_exists(self::SSL_PRIVATE_DIR . '/hostname.key')) {
            ShellService::exec("cat " . self::SSL_PRIVATE_DIR . "/hostname.key > " . self::SSL_PRIVATE_DIR . "/hostname.pem; cat " . self::SSL_CERTS_DIR . "/hostname.bundle >> " . self::SSL_PRIVATE_DIR . "/hostname.pem");
            ShellService::exec("chmod 600 " . self::SSL_PRIVATE_DIR . "/hostname.key; chmod 600 " . self::SSL_PRIVATE_DIR . "/hostname.pem");
            return true;
        }
        return false;
    }

    public static function hostnameSelfSigned(string $hostname, int $keySize = 2048): bool
    {
        ShellService::exec("mkdir -p " . self::SSL_PRIVATE_DIR);

        foreach (['hostname.key', 'hostname.crt', 'hostname.cert', 'hostname.bundle', 'hostname.pem'] as $file) {
            $dir = strpos($file, '.key') !== false || strpos($file, '.pem') !== false ? self::SSL_PRIVATE_DIR : self::SSL_CERTS_DIR;
            if (file_exists("{$dir}/{$file}")) {
                ShellService::exec("unlink {$dir}/{$file}");
            }
        }

        ShellService::exec("openssl req -new -newkey rsa:{$keySize} -nodes -keyout " . self::SSL_PRIVATE_DIR . "/hostname.key -subj '/C=HR/ST=Zagreb/O=CentOS Web Panel/L=HR/CN={$hostname}/OU=CentOS Web Panel/emailAddress=info@centos-webpanel.com' -out " . self::SSL_CERTS_DIR . "/hostname.csr");
        ShellService::exec("openssl req -new -key " . self::SSL_PRIVATE_DIR . "/hostname.key -subj '/C=HR/ST=Zagreb/O=CentOS Web Panel/L=HR/CN={$hostname}/OU=CentOS Web Panel/emailAddress=info@centos-webpanel.com' -x509 -days 365 -out " . self::SSL_CERTS_DIR . "/hostname.bundle");

        if (file_exists(self::SSL_CERTS_DIR . '/hostname.bundle') && file_exists(self::SSL_PRIVATE_DIR . '/hostname.key')) {
            ShellService::exec("cat " . self::SSL_PRIVATE_DIR . "/hostname.key > " . self::SSL_PRIVATE_DIR . "/hostname.pem; cat " . self::SSL_CERTS_DIR . "/hostname.bundle >> " . self::SSL_PRIVATE_DIR . "/hostname.pem");
            ShellService::exec("chmod 600 " . self::SSL_PRIVATE_DIR . "/hostname.key; chmod 600 " . self::SSL_PRIVATE_DIR . "/hostname.pem");
            return true;
        }
        return false;
    }

    protected static function storeDomainConfig(string $domain, string $username, array $san = []): void
    {
        $jsonFile = self::SSL_JSON;
        $config = [];
        if (file_exists($jsonFile) && filesize($jsonFile) > 0) {
            $config = json_decode(file_get_contents($jsonFile), true) ?? [];
        }

        $now = new \DateTime();
        $expiry = (new \DateTime())->modify('+90 day');

        $config[$domain] = [
            'user' => $username,
            'install_date' => $now->format('Y-m-d H:i:s'),
            'expiry_date' => $expiry->format('Y-m-d H:i:s'),
            'san' => $san,
            'autossl' => 'on',
        ];

        file_put_contents($jsonFile, json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);
    }

    protected static function completeRemoveAutoSsl(string $domain): void
    {
        $jsonFile = self::SSL_JSON;
        if (!file_exists($jsonFile)) return;

        $config = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($config) || !isset($config[$domain])) return;

        $domainConfig = $config[$domain];
        if (isset($domainConfig['san']) && is_array($domainConfig['san'])) {
            foreach ($domainConfig['san'] as $san) {
                if ($san === 'mail' || $san === 'webmail') {
                    self::removeDovecotConfig($domain);
                    self::removePostfixConfig($domain);
                }
            }
        }

        unset($config[$domain]);
        file_put_contents($jsonFile, json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);
    }

    protected static function removeSslFiles(string $domain): void
    {
        $paths = [
            self::ACME_CERTS_DIR . '/' . $domain,
            self::SSL_CERTS_DIR . '/' . $domain . '.cert',
            self::SSL_PRIVATE_DIR . '/' . $domain . '.key',
            self::SSL_CERTS_DIR . '/' . $domain . '.bundle',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                ShellService::exec("test -h '{$path}' || rm -f '{$path}'");
            }
        }
    }

    protected static function removeDovecotConfig(string $domain): void
    {
        if (!file_exists(self::DOVECOT_SNI_CONF)) return;
        $content = file_get_contents(self::DOVECOT_SNI_CONF);
        $lines = explode("\n", $content);
        $result = [];
        $skip = false;
        $skipCount = 0;
        foreach ($lines as $line) {
            if (strpos($line, '.' . $domain) !== false) {
                $skip = true;
                $skipCount = 0;
                continue;
            }
            if ($skip) {
                $skipCount++;
                if ($skipCount >= 3) {
                    $skip = false;
                }
                continue;
            }
            $result[] = $line;
        }
        file_put_contents(self::DOVECOT_SNI_CONF, implode("\n", $result));
    }

    protected static function removePostfixConfig(string $domain): void
    {
        if (!file_exists(self::POSTFIX_VMAIL_SSL)) {
            ShellService::exec("touch " . self::POSTFIX_VMAIL_SSL);
        }
        $lines = file(self::POSTFIX_VMAIL_SSL);
        ShellService::exec("> " . self::POSTFIX_VMAIL_SSL);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, $domain . ' /') === false && !empty($line)) {
                ShellService::exec("echo '" . addslashes($line) . "' >> " . self::POSTFIX_VMAIL_SSL);
            }
        }
        ShellService::exec("sed -i '/^\$/d' " . self::POSTFIX_VMAIL_SSL);
        ShellService::exec("/usr/sbin/postmap -F hash:" . self::POSTFIX_VMAIL_SSL);
    }

    public static function getLetsEncryptCerts(): array
    {
        if (!file_exists(self::SSL_JSON)) return [];
        $config = json_decode(file_get_contents(self::SSL_JSON), true);
        if (!is_array($config)) return [];

        $certs = [];
        foreach ($config as $domain => $info) {
            $certs[] = [
                'domain' => $domain,
                'user' => $info['user'] ?? '',
                'install_date' => $info['install_date'] ?? '',
                'expiry_date' => $info['expiry_date'] ?? '',
                'san' => $info['san'] ?? [],
                'autossl' => $info['autossl'] ?? 'off',
            ];
        }
        return $certs;
    }

    public static function letsEncryptIssue(string $domain, string $email = '', string $www = 'www'): string
    {
        return self::autoSslIssue($domain, '', 2048, $www);
    }

    public static function letsEncryptRenew(string $domain = ''): string
    {
        if ($domain) {
            return self::autoSslRenew($domain);
        }
        return ShellService::exec(self::ACME_SH . " --renew-all --force 2>&1");
    }

    public static function letsEncryptRevoke(string $domain): string
    {
        return self::autoSslRevoke($domain);
    }

    public static function installDovecotPostfix2(string $domain, string $keySize = '2048'): bool
    {
        ShellService::exec("mkdir -p " . self::SSL_PRIVATE_DIR);

        $certPath = self::SSL_CERTS_DIR . '/' . $domain . '.bundle';
        $keyPath = self::SSL_PRIVATE_DIR . '/' . $domain . '.key';

        if (!file_exists($certPath) || !file_exists($keyPath)) return false;

        ShellService::exec("cat {$keyPath} {$certPath} > " . self::SSL_CERTS_DIR . "/dovecot.pem");

        $dovecotConf = "/etc/dovecot/conf.d/10-ssl.conf";
        ShellService::replaceInFile($dovecotConf, 'ssl_cert = .*', "ssl_cert = <" . self::SSL_CERTS_DIR . "/dovecot.pem");
        ShellService::replaceInFile($dovecotConf, 'ssl_key = .*', "ssl_key = <" . self::SSL_PRIVATE_DIR . "/dovecot.key");
        ShellService::exec("cp {$keyPath} " . self::SSL_PRIVATE_DIR . "/dovecot.key");

        $postfixMain = '/etc/postfix/main.cf';
        ShellService::replaceInFile($postfixMain, 'smtpd_tls_cert_file = .*', "smtpd_tls_cert_file = " . self::SSL_CERTS_DIR . "/{$domain}.bundle");
        ShellService::replaceInFile($postfixMain, 'smtpd_tls_key_file = .*', "smtpd_tls_key_file = " . self::SSL_PRIVATE_DIR . "/{$domain}.key");

        ServerService::manageServices('restart', ['postfix', 'dovecot']);
        return true;
    }

    public static function autoSslAddSan(string $domain, string $username, array $san = []): string
    {
        if (empty($san)) return 'No SAN domains provided';

        $sanArgs = implode(' ', array_map(fn($s) => '-d ' . $s, $san));
        $output = ShellService::exec(self::ACME_SH . " --issue --cert-home " . self::ACME_CERTS_DIR . " -d {$domain} {$sanArgs} -w /usr/local/apache/autossl_tmp/ --force --log 2>&1");

        if (strpos($output, 'success') !== false || file_exists(self::ACME_CERTS_DIR . "/{$domain}/{$domain}.cer")) {
            self::storeDomainConfig($domain, $username, $san);
            return 'success';
        }
        return $output;
    }

    public static function generateAutoSsl(string $domain, string $username, array $san = ['mail', 'webmail', 'ftp', 'smtp', 'pop', 'imap']): string
    {
        $result = self::autoSslIssue($domain, $username, 2048, 'www', $san);
        if ($result === 'success') {
            self::mailServersAutoSsl($domain);
        }
        return $result;
    }

    public static function mailServersAutoSsl(string $domain): bool
    {
        $certPath = self::SSL_CERTS_DIR . '/' . $domain . '.bundle';
        $keyPath = self::SSL_PRIVATE_DIR . '/' . $domain . '.key';
        if (!file_exists($certPath) || !file_exists($keyPath)) return false;

        ShellService::exec("cat {$keyPath} {$certPath} > " . self::SSL_CERTS_DIR . "/dovecot.pem");
        ShellService::exec("cp {$keyPath} " . self::SSL_PRIVATE_DIR . "/dovecot.key");

        $postfixMain = '/etc/postfix/main.cf';
        ShellService::replaceInFile($postfixMain, 'smtpd_tls_cert_file = .*', "smtpd_tls_cert_file = " . self::SSL_CERTS_DIR . "/{$domain}.bundle");
        ShellService::replaceInFile($postfixMain, 'smtpd_tls_key_file = .*', "smtpd_tls_key_file = " . self::SSL_PRIVATE_DIR . "/{$domain}.key");

        ServerService::manageServices('restart', ['postfix', 'dovecot']);
        return true;
    }

    public static function autoSslGetCertificateErrors(string $domain): string
    {
        $certPath = self::SSL_CERTS_DIR . '/' . $domain . '.bundle';
        if (!file_exists($certPath)) return 'Certificate file not found';
        return ShellService::exec("openssl x509 -in {$certPath} -noout -dates 2>&1");
    }

    public static function uchipRestartMailServers(): void
    {
        ServerService::manageServices('restart', ['postfix', 'dovecot']);
    }

    public static function dameUser(string $domain): ?string
    {
        try {
            $user = \Illuminate\Support\Facades\DB::connection('mysql')->table('user')->where('domain', $domain)->first();
            if ($user) return $user->username;

            $addon = \Illuminate\Support\Facades\DB::connection('mysql')->table('domains')->where('domain', $domain)->first();
            if ($addon) return $addon->user;
        } catch (\Exception $e) {}
        return null;
    }
}

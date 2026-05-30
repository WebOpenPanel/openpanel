<?php

namespace App\Services;

class ApacheBuilderService
{
    const BUILD_LOG = '/var/log/apache-rebuild.log';

    public static function getCurrentVersion(): string
    {
        return trim(ShellService::exec('/usr/local/apache/bin/httpd -v 2>/dev/null | head -1'));
    }

    public static function getLoadedModules(): string
    {
        return ShellService::exec('/usr/local/apache/bin/httpd -M 2>&1');
    }

    public static function getAvailableVersions(): array
    {
        return [
            '2.4.23' => 'Apache 2.4.23',
            '2.4.25' => 'Apache 2.4.25',
            '2.4.26' => 'Apache 2.4.26',
            '2.4.27' => 'Apache 2.4.27',
            '2.4.28' => 'Apache 2.4.28',
            '2.4.29' => 'Apache 2.4.29',
            '2.4.33' => 'Apache 2.4.33',
            '2.4.34' => 'Apache 2.4.34',
            '2.4.37' => 'Apache 2.4.37',
            '2.4.39' => 'Apache 2.4.39',
            '2.4.41' => 'Apache 2.4.41',
            '2.4.43' => 'Apache 2.4.43',
            '2.4.46' => 'Apache 2.4.46',
            '2.4.51' => 'Apache 2.4.51',
            '2.4.52' => 'Apache 2.4.52',
            '2.4.54' => 'Apache 2.4.54',
            '2.4.56' => 'Apache 2.4.56',
            '2.4.57' => 'Apache 2.4.57',
            '2.4.58' => 'Apache 2.4.58',
        ];
    }

    public static function getDefaultConfigure(): string
    {
        return "./configure \n--enable-so \n--prefix=/usr/local/apache \n--enable-unique-id \n--enable-ssl=shared \n--enable-rewrite  \n--enable-deflate \n--enable-suexec \n--with-suexec-docroot=\"/home\" \n--with-suexec-caller=\"nobody\" \n--with-suexec-logfile=\"/usr/local/apache/logs/suexec_log\" \n--enable-asis \n--enable-filter \n--with-pcre \n--with-apr=/usr/bin/apr-1-config \n--with-apr-util=/usr/bin/apu-1-config \n--enable-headers \n--enable-expires \n--enable-proxy \n--enable-rewrite \n--enable-userdir";
    }

    public static function startBuild(string $version, string $addons, bool $modH264 = false): string
    {
        $centosVersion = trim(ShellService::exec("cat /etc/redhat-release | awk '{print \$4}' | cut -d. -f1"));
        ShellService::exec('rm -f /usr/local/src/apache-rebuild.sh /var/log/apache-rebuild.log');
        ShellService::exec('cd /usr/local/src/;wget http://dl1.centos-webpanel.com/files/c_scripts/el' . $centosVersion . '/apache-rebuild.sh 2>&1');
        ShellService::exec('sed -i "s|CONFIGURE_BUILD_VERSION.*|' . $version . '|g" /usr/local/src/apache-rebuild.sh');
        $addons = str_replace("\n", ' ', $addons);
        ShellService::exec('sed -i "s|CONFIGURE_BUILD_COMMAND.*|' . $addons . '|g" /usr/local/src/apache-rebuild.sh');

        if ($modH264) {
            ShellService::exec('cd /usr/local/src/;wget dl1.centos-webpanel.com/files/c_scripts/h264_flvx.sh 2>&1');
            ShellService::exec('sed -i "s|#ADDITIONAL_CONFIGURATION.*|. /usr/local/src/h264_flvx.sh;|g" /usr/local/src/apache-rebuild.sh');
        }

        if (!file_exists('/usr/bin/screen')) {
            ShellService::exec('yum -y install screen 2>&1');
        }

        ShellService::execBackground('screen -d -m -L -S apache_rebuild sh -c "sh /usr/local/src/apache-rebuild.sh | tee /var/log/apache-rebuild.log"');
        return 'Apache build started in background. Monitor: tail -f /var/log/apache-rebuild.log';
    }

    public static function getBuildLog(): string
    {
        return ShellService::exec('tail -n 50 ' . self::BUILD_LOG . ' 2>/dev/null');
    }
}

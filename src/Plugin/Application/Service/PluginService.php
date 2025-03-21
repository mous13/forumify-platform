<?php

declare(strict_types=1);

namespace Forumify\Plugin\Application\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Forumify\Plugin\Application\Exception\PluginException;
use Forumify\Plugin\Application\Exception\PluginNotFoundException;
use JsonException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class PluginService
{
    private readonly Connection $connection;
    private readonly FrameworkCacheClearer $frameworkCacheClearer;
    private readonly string $rootDir;

    /**
     * @param array{DATABASE_URL: string, DOCUMENT_ROOT: string} $context
     * @throws Exception
     */
    public function __construct(array $context)
    {
        $this->connection = DriverManager::getConnection([
            'url' => $context['DATABASE_URL'],
        ]);

        $this->rootDir = dirname($context['DOCUMENT_ROOT']);
        $this->frameworkCacheClearer = new FrameworkCacheClearer($this->rootDir);
    }

    /**
     * @throws PluginException|JsonException
     */
    public function composerRequire(string $package): string
    {
        if ($versionPos = strpos($package, ':')) {
            $package = substr($package, 0, $versionPos);
        }
        $this->verifyPackage($package);
        $package .= ':*';

        return $this->run([
            'composer',
            'require',
            $package,
            '--no-interaction',
            '--no-scripts',
        ]);
    }

    /**
     * Probably a temporary measure until marketplace is released to restrict plugins
     *
     * @throws PluginException|JsonException
     */
    private function verifyPackage(string $package): void
    {
        $isDemo = (bool)($_SERVER['FORUMIFY_DEMO'] ?? false);
        $isCloudInstance = (bool)($_SERVER['FORUMIFY_HOSTED_INSTANCE'] ?? false);
        if (!$isDemo && !$isCloudInstance) {
            return;
        }

        $allowedPluginsJson = file_get_contents('https://forumify.net/allowed-plugins');
        if ($allowedPluginsJson === false) {
            throw new PluginException("Unable to check if $package can be installed on cloud/demo instances.");
        }

        /** @var array<string, string[]> $allowedPlugins */
        $allowedPlugins = json_decode($allowedPluginsJson, true, 512, JSON_THROW_ON_ERROR);
        $pluginAvailability = $allowedPlugins[$package] ?? null;
        if ($pluginAvailability === null) {
            throw new PluginException("$package is only installable on self-hosted versions of forumify.");
        }

        if ($isDemo && !in_array('demo', $pluginAvailability, true)) {
            throw new PluginException("$package is not available on demo versions of forumify.");
        }

        if ($isCloudInstance && !in_array('hosted', $pluginAvailability, true)) {
            throw new PluginException("$package is not available on forumify cloud. Either contact support to get the package whitelisted or use the self-hosted version of forumify.");
        }
    }

    public function composerUpdate(?string $package = null): string
    {
        $cmd = ['composer', 'update'];
        if ($package !== null) {
            $cmd[] = $package;
            $cmd[] = '--with-all-dependencies';
        }

        return $this->run([
            ...$cmd,
            '--no-interaction',
            '--no-scripts',
        ]);
    }

    public function composerRemove(string $package): string
    {
        return $this->run([
            'composer',
            'remove',
            $package,
            '--no-interaction',
            '--no-scripts',
        ]);
    }

    public function composerPostInstall(): string
    {
        $output = [];
        if ($_SERVER['APP_ENV'] === 'prod') {
            $output[] = $this->run([
                'composer',
                'dump-autoload',
                '--optimize',
                '--classmap-authoritative',
            ]);
        }

        $output[] = $this->run([
            'composer',
            'run-script',
            'auto-scripts',
            '--no-interaction',
        ]);

        return implode(PHP_EOL, $output);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getLatestVersions(string $rootDir): array
    {
        $process = new Process([
            'composer',
            'outdated',
            '--all',
            '--direct',
            '--format',
            'json',
            '--working-dir',
            $rootDir,
        ]);
        $process->run();
        $output = $process->getOutput();

        try {
            $versions = json_decode($output, true, 512, JSON_THROW_ON_ERROR)['installed'] ?? [];
        } catch (JsonException) {
            // a lot more needs to be broken before this can happen...
            $versions = null;
        }

        if ($versions === null) {
            return [];
        }
        return array_combine(array_column($versions, 'name'), $versions);
    }

    /**
     * @throws PluginException
     */
    public function setActive(int $pluginId, bool $active): string
    {
        try {
            $updated = (int)$this->connection->executeStatement('UPDATE plugin SET active = :active WHERE id = :id', [
                'active' => (int)$active,
                'id' => $pluginId,
            ]);
        } catch (Exception $ex) {
            throw new PluginException('Unable to execute query: ' . $ex->getMessage(), 0, $ex);
        }

        if ($updated < 1) {
            throw new PluginNotFoundException($pluginId);
        }

        return "Plugin $pluginId " . ($active ? 'activated' : 'deactivated');
    }

    /**
     * @throws PluginException
     */
    public function clearFrameworkCache(): string
    {
        try {
            $this->frameworkCacheClearer->clear();
            return 'Cache removed.';
        } catch (\Exception $ex) {
            throw new PluginException('Unable to clear cache: ' . $ex->getMessage(), 0, $ex);
        }
    }

    public function migrations(): string
    {
        return $this->run([
            'php',
            'bin/console',
            'doctrine:migrations:migrate',
            '--allow-no-migration',
            '--no-interaction'
        ]);
    }

    /**
     * @throws JsonException|PluginException
     */
    public function npmUpdate(): string
    {
        $output = '';

        // first we need to delete linked files because npm is ass at updating local files
        $packageJson = file_get_contents($this->rootDir . DIRECTORY_SEPARATOR . 'package.json');
        if ($packageJson === false) {
            throw new PluginException('Unable to find package.json. Nothing to update...');
        }

        $fs = new Filesystem();
        $packageJson = json_decode($packageJson, true, 512, JSON_THROW_ON_ERROR);
        $packages = array_merge($packageJson['dependencies'] ?? [], $packageJson['devDependencies'] ?? []);

        foreach ($packages as $package => $version) {
            if (!str_starts_with($version, 'file:')) {
                continue;
            }
            $path = [$this->rootDir, 'node_modules', ...explode('/', $package)];
            $dir = implode(DIRECTORY_SEPARATOR, $path);
            $fs->remove($dir);
            $output .= "Removed $dir.\n";
        }

        $output .= $this->run([
            'npm',
            'install',
        ]);
        return $output;
    }

    public function npmBuild(): string
    {
        return $this->run([
            'npm',
            'run',
            'build',
        ]);
    }

    /**
     * @param non-empty-array<string> $cmd
     */
    private function run(array $cmd): string
    {
        $process = new Process($cmd, $this->rootDir);
        $process->mustRun();
        return $process->getOutput() . "\n" . $process->getErrorOutput();
    }
}

<?php

/*
 * This file is part of the Fxp RequireAssetBundle package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Bundle\RequireAssetBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Adds all NPM and Bower package installed by Composer.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class ComposerAssetsPass implements CompilerPassInterface
{
    /**
     * @var array
     */
    protected $types = array('npm', 'bower');

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $baseDir = $container->getParameter('fxp_require_asset.base_dir');
        $installedPath = $container->getParameter('fxp_require_asset.composer_installed_path');

        if (!file_exists($baseDir.'/composer.json') || !file_exists($installedPath)) {
            return;
        }

        $composerInstalled = $this->getComposerInstalled($installedPath);
        $packageManagerDef = $container->getDefinition('fxp_require_asset.assetic.config.package_manager');
        $packages = array();

        foreach ($this->types as $type) {
            $path = $this->getAssetBasePath($type, $baseDir);
            $packages = array_merge($packages, $this->findAssetPackages($type, $path, $composerInstalled));
        }

        $this->addPackages($packageManagerDef, $packages);
    }

    /**
     * Gets the composer installed packages.
     *
     * @param string $filename The filename of installed packages
     *
     * @return array
     */
    protected function getComposerInstalled($filename)
    {
        return json_decode(file_get_contents($filename), true);
    }

    /**
     * Gets the asset base path.
     *
     * @param string $type    The asset type
     * @param string $baseDir The base directory of project
     *
     * @return string
     */
    protected function getAssetBasePath($type, $baseDir)
    {
        $composer = json_decode(file_get_contents($baseDir.'/composer.json'), true);

        if (isset($composer['extra']['asset-installer-paths'][$type.'-asset-library'])) {
            $path = $baseDir.'/';
            $path .= $composer['extra']['asset-installer-paths'][$type.'-asset-library'];

            return rtrim($path, '/');
        }

        return $baseDir.'/vendor/'.$type.'-asset';
    }

    /**
     * Finds the source paths of asset packages.
     *
     * @param string $type      The asset type
     * @param string $path      The path of source asset type
     * @param array  $installed The installed composer packages
     *
     * @return array The map of asset package name and path
     */
    protected function findAssetPackages($type, $path, array $installed)
    {
        $packages = array();

        foreach ($installed as $package) {
            $name = $package['name'];
            $prefix = $type.'-asset/';

            if (0 === strpos($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                $name = str_replace(array('[', ']'), '-', $name);
                $packages['@'.$type.'/'.$name] = $path.'/'.$name;
            }
        }

        return $packages;
    }

    /**
     * Adds composer package.
     *
     * @param Definition $packageManagerDef
     * @param array      $packages
     */
    protected function addPackages(Definition $packageManagerDef, array $packages)
    {
        foreach ($packages as $name => $path) {
            $package = array(
                'name'        => $name,
                'source_path' => $path,
                'source_base' => null,
            );
            $packageManagerDef->addMethodCall('addPackage', array($package));
        }
    }
}

<?php namespace Chaos\Common\Traits;

use Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\ContainerInterface,
    Symfony\Component\Config\FileLocator,
    Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Trait ContainerAwareTrait
 * @author ntd1712
 */
trait ContainerAwareTrait
{
    /** @var ContainerInterface */
    private static $__container__;

    /**
     * Get a reference to the container object. The object returned will be of type <tt>ContainerInterface</tt>
     *
     * @return  ContainerBuilder|ContainerInterface
     */
    public function getContainer()
    {
        return self::$__container__;
    }

    /**
     * Set a reference to the container object
     *
     * @param   array|\ArrayAccess|ContainerInterface $container
     * @return  $this
     */
    public function setContainer($container)
    {
        if (!$container instanceof ContainerInterface)
        {
            $definitions = $container;
            $container = new ContainerBuilder;

            if (is_array($definitions) || $definitions instanceof \ArrayAccess)
            {
                $loader = new YamlFileLoader($container, new FileLocator($definitions));
                array_walk($definitions, [$loader, 'load']);
            }
        }

        self::$__container__ = $container;
        return $this;
    }
}
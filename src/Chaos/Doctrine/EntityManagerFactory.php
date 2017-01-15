<?php namespace Chaos\Doctrine;

use Doctrine\Common\Cache,
    Doctrine\Common\EventManager,
    Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver,
    Doctrine\DBAL\Types\Type,
    Doctrine\ORM\Configuration,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\Events,
    Doctrine\ORM\Mapping\Driver\XmlDriver,
    Doctrine\ORM\Mapping\Driver\YamlDriver,
    Doctrine\ORM\Tools\Setup,
    Chaos\Common\Exceptions\RuntimeException,
    Chaos\Common\Traits\ConfigAwareTrait,
    Chaos\Doctrine\Extensions\TablePrefix;

/**
 * Class EntityManagerFactory
 * @author ntd1712
 */
class EntityManagerFactory
{
    use ConfigAwareTrait;

    /** @var EntityManager|\Doctrine\ORM\EntityManagerInterface */
    protected static $entityManager;

    /**
     * @return  EntityManager|\Doctrine\ORM\EntityManagerInterface
     */
    public function getEntityManager()
    {
        if (null === self::$entityManager)
        {
            self::$entityManager = EntityManager::create($config = $this->getDbParams(),
                $this->getConfiguration($this->getCacheProvider()), $this->getEventManager(@$config['prefix']));
        }

        return self::$entityManager;
    }

    /**
     * Create an instance of the class
     *
     * @return  $this
     */
    public static function create()
    {
        return new static;
    }

    /**
     * @return  array
     */
    protected function getDbParams()
    {
        $config = $this->getConfig()->get('db.connections')[$this->getConfig()->get('db.default')];
        $drivers = (new \ReflectionClass(DOCTRINE_DRIVER_MANAGER))->getStaticProperties()['driverSchemeAliases'];

        if (isset($drivers[$config['driver']]))
        {
            $config['driver'] = $drivers[$config['driver']];
        }

        if (!isset($config['user']) && isset($config['username']))
        {
            $config['user'] = $config['username'];
        }

        if (!isset($config['password']) && isset($config['pass']))
        {
            $config['password'] = $config['pass'];
        }

        if (!isset($config['dbname']) && isset($config['database']))
        {
            $config['dbname'] = $config['database'];
        }

        return $config;
    }

    /**
     * @return  Cache\Cache
     */
    protected function getCacheProvider()
    {
        $config = $this->getConfig()->get('orm.cache');

        switch ($config['provider'])
        {
            case 'array':
                return new Cache\ArrayCache;
            case 'file':
                return new Cache\FilesystemCache($config[$config['provider']]['directory'],
                    $config[$config['provider']]['extension']);
            case 'redis':
                $redis = new \Redis;
                $redis->connect($config[$config['provider']]['host'], $config[$config['provider']]['port'],
                    $config[$config['provider']]['timeout'], $config[$config['provider']]['retry_interval']);
                $redis->select($config[$config['provider']]['dbIndex']);

                $cache = new Cache\RedisCache;
                $cache->setRedis($redis);

                return $cache;
            case 'memcached':
                $memcache = new \Memcache;
                $memcache->connect($config[$config['provider']]['host'], $config[$config['provider']]['port'],
                    $config[$config['provider']]['timeout']);

                $cache = new Cache\MemcacheCache;
                $cache->setMemcache($memcache);

                return $cache;
            default:
                return null;
        }
    }

    /**
     * @param   Cache\Cache $cache
     * @return  Configuration
     */
    protected function getConfiguration(Cache\Cache $cache = null)
    {
        $config = $this->getConfig()->get('orm');
        $configuration = Setup::createConfiguration($this->getConfig()->get('app.debug'),
            $config['proxy_classes']['directory'], $cache);

        $configuration->setMetadataDriverImpl(self::getMetadataDriver($configuration, $config['metadata']));

        if (isset($config['dql']['datetime_functions']))
        {
            $configuration->setCustomDatetimeFunctions((array)$config['dql']['datetime_functions']);
        }

        if (isset($config['dql']['numeric_functions']))
        {
            $configuration->setCustomNumericFunctions((array)$config['dql']['numeric_functions']);
        }

        if (isset($config['dql']['string_functions']))
        {
            $configuration->setCustomStringFunctions((array)$config['dql']['string_functions']);
        }

        if (isset($config['dql']['mapping_types']))
        {
            foreach ($config['dql']['mapping_types'] as $name => $className)
            {
                Type::hasType($name) ? Type::overrideType($name, $className) : Type::addType($name, $className);
            }

            $configuration->setCustomStringFunctions((array)$config['dql']['string_functions']);
        }

        if (null !== $cache)
        {
            $configuration->setMetadataCacheImpl($cache);
            $configuration->setQueryCacheImpl($cache);
            $configuration->setResultCacheImpl($cache);
        }

        if (isset($config['proxy_classes']['namespace']))
        {
            $configuration->setProxyNamespace($config['proxy_classes']['namespace']);
        }

        $configuration->setAutoGenerateProxyClasses($config['proxy_classes']['auto_generate']);
        $configuration->setDefaultRepositoryClassName($config['default_repository']);
        $configuration->setSQLLogger($config['sql_logger']);

        return $configuration;
    }

    /**
     * @param   Configuration $config
     * @param   array $metadata
     * @return  \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver
     * @throws  RuntimeException
     */
    protected static function getMetadataDriver(Configuration $config, $metadata)
    {
        switch ($metadata['driver'])
        {
            case 'annotation':
                return $config->newDefaultAnnotationDriver($metadata['paths'], $metadata['simple']);
            case 'yaml':
                return new YamlDriver($metadata['paths']);
            case 'xml':
                return new XmlDriver($metadata['paths']);
            case 'static':
                return new StaticPHPDriver($metadata['paths']);
            default:
                throw new RuntimeException(sprintf('Unsupported driver: %s', $metadata['driver']));
        }
    }

    /**
     * @param   string $prefix
     * @return  EventManager
     */
    protected function getEventManager($prefix = null)
    {
        $eventManager = new EventManager;

        if (null !== $prefix)
        {
            $eventManager->addEventListener(Events::loadClassMetadata, new TablePrefix($prefix));
        }

        return $eventManager;
    }
}
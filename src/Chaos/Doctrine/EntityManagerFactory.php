<?php namespace Chaos\Doctrine;

use Doctrine\Common\Cache\ArrayCache,
    Doctrine\Common\Cache\CacheProvider,
    Doctrine\Common\Cache\FilesystemCache,
    Doctrine\Common\Cache\MemcacheCache,
    Doctrine\Common\Cache\RedisCache,
    Doctrine\Common\EventManager,
    Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver,
    Doctrine\ORM\Configuration,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\Events,
    Doctrine\ORM\Mapping\Driver\XmlDriver,
    Doctrine\ORM\Mapping\Driver\YamlDriver,
    Doctrine\ORM\Tools\Setup,
    Chaos\Common\Traits\ConfigAwareTrait,
    Chaos\Doctrine\Extensions\TablePrefix;

/**
 * Class EntityManagerFactory
 * @author ntd1712
 */
class EntityManagerFactory
{
    use ConfigAwareTrait;

    /** @var EntityManager */
    protected static $entityManager;

    /**
     * @return  EntityManager
     */
    public function getEntityManager()
    {
        if (null === self::$entityManager)
        {
            self::$entityManager = EntityManager::create($this->getConfig()->get('db'),
                $this->getConfiguration($this->getCacheProvider()), $this->getEventManager());
        }

        return self::$entityManager;
    }

    /**
     * @return  CacheProvider
     */
    protected function getCacheProvider()
    {
        $config = $this->getConfig()->get('orm.cache');

        switch ($config['provider'])
        {
            case 'array':
                return new ArrayCache;
            case 'filesystem':
                return new FilesystemCache($config[$config['provider']]['directory'],
                    $config[$config['provider']]['extension']);
            case 'redis':
                $redis = new \Redis;
                $redis->connect($config[$config['provider']]['host'], $config[$config['provider']]['port']);
                $redis->select($config[$config['provider']]['dbindex']);

                $cache = new RedisCache;
                $cache->setRedis($redis);

                return $cache;
            case 'memcached':
                $memcache = new \Memcache;
                $memcache->connect($config[$config['provider']]['host'], $config[$config['provider']]['port'],
                    $config[$config['provider']]['weight']);

                $cache = new MemcacheCache;
                $cache->setMemcache($memcache);

                return $cache;
            default:
                return null;
        }
    }

    /**
     * @param   CacheProvider $cache
     * @return  Configuration
     */
    protected function getConfiguration(CacheProvider $cache = null)
    {
        $orm = $this->getConfig()->get('orm');
        $configuration = Setup::createConfiguration($orm['is_dev_mode'], $orm['proxy']['dir'], $cache);

        $configuration->setMetadataDriverImpl(self::getMetadataDriver($configuration, $orm['metadata']));
        $configuration->setCustomNumericFunctions([
            'ACOS' => 'DoctrineExtensions\Query\Mysql\Acos',
            'ASIN' => 'DoctrineExtensions\Query\Mysql\Asin',
            'ATAN' => 'DoctrineExtensions\Query\Mysql\Atan',
            'ATAN2' => 'DoctrineExtensions\Query\Mysql\Atan2',
            'COS' => 'DoctrineExtensions\Query\Mysql\Cos',
            'COT' => 'DoctrineExtensions\Query\Mysql\Cot',
            'DEGREES' => 'DoctrineExtensions\Query\Mysql\Degrees',
            'RADIANS' => 'DoctrineExtensions\Query\Mysql\Radians',
            'SIN' => 'DoctrineExtensions\Query\Mysql\Sin',
            'TAN' => 'DoctrineExtensions\Query\Mysql\Tan'
        ]);

        if (null !== $cache)
        {
            $configuration->setMetadataCacheImpl($cache);
            $configuration->setQueryCacheImpl($cache);
            $configuration->setResultCacheImpl($cache);
        }

        if (isset($orm['proxy']['namespace']))
        {
            $configuration->setProxyNamespace($orm['proxy']['namespace']);
        }

        $configuration->setAutoGenerateProxyClasses($orm['proxy']['auto_generate']);
        $configuration->setDefaultRepositoryClassName($orm['default_repository']);
        $configuration->setSQLLogger($orm['sql_logger']);

        return $configuration;
    }

    /**
     * @param   Configuration $config
     * @param   array $metadata
     * @return  \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver
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
                throw new \RuntimeException(sprintf('Unsupported driver: %s', $metadata['driver']));
        }
    }

    /**
     * @return  EventManager
     */
    protected function getEventManager()
    {
        $eventManager = new EventManager;

        if ($prefix = $this->getConfig()->get('db.dbprefix'))
        {
            $eventManager->addEventListener(Events::loadClassMetadata, new TablePrefix($prefix));
        }

        return $eventManager;
    }
}
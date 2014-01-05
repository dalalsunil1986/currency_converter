<?php  



namespace Symfony\Bundle\FrameworkBundle\EventListener
{

use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;
use Symfony\Component\DependencyInjection\ContainerInterface;


class SessionListener extends BaseSessionListener
{
    
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getSession()
    {
        if (!$this->container->has('session')) {
            return null;
        }

        return $this->container->get('session');
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session\Storage
{

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;


class NativeSessionStorage implements SessionStorageInterface
{
    
    protected $bags;

    
    protected $started = false;

    
    protected $closed = false;

    
    protected $saveHandler;

    
    protected $metadataBag;

    
    public function __construct(array $options = array(), $handler = null, MetadataBag $metaBag = null)
    {
        session_cache_limiter('');         ini_set('session.use_cookies', 1);

        if (version_compare(phpversion(), '5.4.0', '>=')) {
            session_register_shutdown();
        } else {
            register_shutdown_function('session_write_close');
        }

        $this->setMetadataBag($metaBag);
        $this->setOptions($options);
        $this->setSaveHandler($handler);
    }

    
    public function getSaveHandler()
    {
        return $this->saveHandler;
    }

    
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        if (version_compare(phpversion(), '5.4.0', '>=') && \PHP_SESSION_ACTIVE === session_status()) {
            throw new \RuntimeException('Failed to start the session: already started by PHP.');
        }

        if (version_compare(phpversion(), '5.4.0', '<') && isset($_SESSION) && session_id()) {
                        throw new \RuntimeException('Failed to start the session: already started by PHP ($_SESSION is set).');
        }

        if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
        }

                if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        $this->loadSession();
        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
                        $this->saveHandler->setActive(true);
        }

        return true;
    }

    
    public function getId()
    {
        if (!$this->started && !$this->closed) {
            return '';         }

        return $this->saveHandler->getId();
    }

    
    public function setId($id)
    {
        $this->saveHandler->setId($id);
    }

    
    public function getName()
    {
        return $this->saveHandler->getName();
    }

    
    public function setName($name)
    {
        $this->saveHandler->setName($name);
    }

    
    public function regenerate($destroy = false, $lifetime = null)
    {
        if (null !== $lifetime) {
            ini_set('session.cookie_lifetime', $lifetime);
        }

        if ($destroy) {
            $this->metadataBag->stampNew();
        }

        $ret = session_regenerate_id($destroy);

                if ('files' === $this->getSaveHandler()->getSaveHandlerName()) {
            session_write_close();
            if (isset($_SESSION)) {
                $backup = $_SESSION;
                session_start();
                $_SESSION = $backup;
            } else {
                session_start();
            }

            $this->loadSession();
        }

        return $ret;
    }

    
    public function save()
    {
        session_write_close();

        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
                        $this->saveHandler->setActive(false);
        }

        $this->closed = true;
        $this->started = false;
    }

    
    public function clear()
    {
                foreach ($this->bags as $bag) {
            $bag->clear();
        }

                $_SESSION = array();

                $this->loadSession();
    }

    
    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getName()] = $bag;
    }

    
    public function getBag($name)
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
        }

        if ($this->saveHandler->isActive() && !$this->started) {
            $this->loadSession();
        } elseif (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    
    public function setMetadataBag(MetadataBag $metaBag = null)
    {
        if (null === $metaBag) {
            $metaBag = new MetadataBag();
        }

        $this->metadataBag = $metaBag;
    }

    
    public function getMetadataBag()
    {
        return $this->metadataBag;
    }

    
    public function isStarted()
    {
        return $this->started;
    }

    
    public function setOptions(array $options)
    {
        $validOptions = array_flip(array(
            'cache_limiter', 'cookie_domain', 'cookie_httponly',
            'cookie_lifetime', 'cookie_path', 'cookie_secure',
            'entropy_file', 'entropy_length', 'gc_divisor',
            'gc_maxlifetime', 'gc_probability', 'hash_bits_per_character',
            'hash_function', 'name', 'referer_check',
            'serialize_handler', 'use_cookies',
            'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled',
            'upload_progress.cleanup', 'upload_progress.prefix', 'upload_progress.name',
            'upload_progress.freq', 'upload_progress.min-freq', 'url_rewriter.tags',
        ));

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                ini_set('session.'.$key, $value);
            }
        }
    }

    
    public function setSaveHandler($saveHandler = null)
    {
        if (!$saveHandler instanceof AbstractProxy &&
            !$saveHandler instanceof NativeSessionHandler &&
            !$saveHandler instanceof \SessionHandlerInterface &&
            null !== $saveHandler) {
            throw new \InvalidArgumentException('Must be instance of AbstractProxy or NativeSessionHandler; implement \SessionHandlerInterface; or be null.');
        }

                if (!$saveHandler instanceof AbstractProxy && $saveHandler instanceof \SessionHandlerInterface) {
            $saveHandler = new SessionHandlerProxy($saveHandler);
        } elseif (!$saveHandler instanceof AbstractProxy) {
            $saveHandler = version_compare(phpversion(), '5.4.0', '>=') ?
                new SessionHandlerProxy(new \SessionHandler()) : new NativeProxy();
        }
        $this->saveHandler = $saveHandler;

        if ($this->saveHandler instanceof \SessionHandlerInterface) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                session_set_save_handler($this->saveHandler, false);
            } else {
                session_set_save_handler(
                    array($this->saveHandler, 'open'),
                    array($this->saveHandler, 'close'),
                    array($this->saveHandler, 'read'),
                    array($this->saveHandler, 'write'),
                    array($this->saveHandler, 'destroy'),
                    array($this->saveHandler, 'gc')
                );
            }
        }
    }

    
    protected function loadSession(array &$session = null)
    {
        if (null === $session) {
            $session = &$_SESSION;
        }

        $bags = array_merge($this->bags, array($this->metadataBag));

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) ? $session[$key] : array();
            $bag->initialize($session[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session\Storage
{

use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;


class PhpBridgeSessionStorage extends NativeSessionStorage
{
    
    public function __construct($handler = null, MetadataBag $metaBag = null)
    {
        $this->setMetadataBag($metaBag);
        $this->setSaveHandler($handler);
    }

    
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        $this->loadSession();
        if (!$this->saveHandler->isWrapper() && !$this->saveHandler->isSessionHandlerInterface()) {
                        $this->saveHandler->setActive(true);
        }

        return true;
    }

    
    public function clear()
    {
                        foreach ($this->bags as $bag) {
            $bag->clear();
        }

                $this->loadSession();
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session\Storage\Handler
{


class NativeFileSessionHandler extends NativeSessionHandler
{
    
    public function __construct($savePath = null)
    {
        if (null === $savePath) {
            $savePath = ini_get('session.save_path');
        }

        $baseDir = $savePath;

        if ($count = substr_count($savePath, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(sprintf('Invalid argument $savePath \'%s\'', $savePath));
            }

                        $baseDir = ltrim(strrchr($savePath, ';'), ';');
        }

        if ($baseDir && !is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        ini_set('session.save_path', $savePath);
        ini_set('session.save_handler', 'files');
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy
{


abstract class AbstractProxy
{
    
    protected $wrapper = false;

    
    protected $active = false;

    
    protected $saveHandlerName;

    
    public function getSaveHandlerName()
    {
        return $this->saveHandlerName;
    }

    
    public function isSessionHandlerInterface()
    {
        return ($this instanceof \SessionHandlerInterface);
    }

    
    public function isWrapper()
    {
        return $this->wrapper;
    }

    
    public function isActive()
    {
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            return $this->active = \PHP_SESSION_ACTIVE === session_status();
        }

        return $this->active;
    }

    
    public function setActive($flag)
    {
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            throw new \LogicException('This method is disabled in PHP 5.4.0+');
        }

        $this->active = (bool) $flag;
    }

    
    public function getId()
    {
        return session_id();
    }

    
    public function setId($id)
    {
        if ($this->isActive()) {
            throw new \LogicException('Cannot change the ID of an active session');
        }

        session_id($id);
    }

    
    public function getName()
    {
        return session_name();
    }

    
    public function setName($name)
    {
        if ($this->isActive()) {
            throw new \LogicException('Cannot change the name of an active session');
        }

        session_name($name);
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy
{


class SessionHandlerProxy extends AbstractProxy implements \SessionHandlerInterface
{
    
    protected $handler;

    
    public function __construct(\SessionHandlerInterface $handler)
    {
        $this->handler = $handler;
        $this->wrapper = ($handler instanceof \SessionHandler);
        $this->saveHandlerName = $this->wrapper ? ini_get('session.save_handler') : 'user';
    }

    
    
    public function open($savePath, $sessionName)
    {
        $return = (bool) $this->handler->open($savePath, $sessionName);

        if (true === $return) {
            $this->active = true;
        }

        return $return;
    }

    
    public function close()
    {
        $this->active = false;

        return (bool) $this->handler->close();
    }

    
    public function read($id)
    {
        return (string) $this->handler->read($id);
    }

    
    public function write($id, $data)
    {
        return (bool) $this->handler->write($id, $data);
    }

    
    public function destroy($id)
    {
        return (bool) $this->handler->destroy($id);
    }

    
    public function gc($maxlifetime)
    {
        return (bool) $this->handler->gc($maxlifetime);
    }
}
}
 



namespace Symfony\Component\HttpFoundation\Session
{

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;


class Session implements SessionInterface, \IteratorAggregate, \Countable
{
    
    protected $storage;

    
    private $flashName;

    
    private $attributeName;

    
    public function __construct(SessionStorageInterface $storage = null, AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        $this->storage = $storage ?: new NativeSessionStorage();

        $attributes = $attributes ?: new AttributeBag();
        $this->attributeName = $attributes->getName();
        $this->registerBag($attributes);

        $flashes = $flashes ?: new FlashBag();
        $this->flashName = $flashes->getName();
        $this->registerBag($flashes);
    }

    
    public function start()
    {
        return $this->storage->start();
    }

    
    public function has($name)
    {
        return $this->storage->getBag($this->attributeName)->has($name);
    }

    
    public function get($name, $default = null)
    {
        return $this->storage->getBag($this->attributeName)->get($name, $default);
    }

    
    public function set($name, $value)
    {
        $this->storage->getBag($this->attributeName)->set($name, $value);
    }

    
    public function all()
    {
        return $this->storage->getBag($this->attributeName)->all();
    }

    
    public function replace(array $attributes)
    {
        $this->storage->getBag($this->attributeName)->replace($attributes);
    }

    
    public function remove($name)
    {
        return $this->storage->getBag($this->attributeName)->remove($name);
    }

    
    public function clear()
    {
        $this->storage->getBag($this->attributeName)->clear();
    }

    
    public function isStarted()
    {
        return $this->storage->isStarted();
    }

    
    public function getIterator()
    {
        return new \ArrayIterator($this->storage->getBag($this->attributeName)->all());
    }

    
    public function count()
    {
        return count($this->storage->getBag($this->attributeName)->all());
    }

    
    public function invalidate($lifetime = null)
    {
        $this->storage->clear();

        return $this->migrate(true, $lifetime);
    }

    
    public function migrate($destroy = false, $lifetime = null)
    {
        return $this->storage->regenerate($destroy, $lifetime);
    }

    
    public function save()
    {
        $this->storage->save();
    }

    
    public function getId()
    {
        return $this->storage->getId();
    }

    
    public function setId($id)
    {
        $this->storage->setId($id);
    }

    
    public function getName()
    {
        return $this->storage->getName();
    }

    
    public function setName($name)
    {
        $this->storage->setName($name);
    }

    
    public function getMetadataBag()
    {
        return $this->storage->getMetadataBag();
    }

    
    public function registerBag(SessionBagInterface $bag)
    {
        $this->storage->registerBag($bag);
    }

    
    public function getBag($name)
    {
        return $this->storage->getBag($name);
    }

    
    public function getFlashBag()
    {
        return $this->getBag($this->flashName);
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Templating
{

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;


class GlobalVariables
{
    protected $container;

    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
    public function getSecurity()
    {
        if ($this->container->has('security.context')) {
            return $this->container->get('security.context');
        }
    }

    
    public function getUser()
    {
        if (!$security = $this->getSecurity()) {
            return;
        }

        if (!$token = $security->getToken()) {
            return;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return;
        }

        return $user;
    }

    
    public function getRequest()
    {
        if ($this->container->has('request_stack')) {
            return $this->container->get('request_stack')->getCurrentRequest();
        }
    }

    
    public function getSession()
    {
        if ($request = $this->getRequest()) {
            return $request->getSession();
        }
    }

    
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
    }

    
    public function getDebug()
    {
        return (Boolean) $this->container->getParameter('kernel.debug');
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Templating
{

use Symfony\Component\Templating\TemplateReference as BaseTemplateReference;


class TemplateReference extends BaseTemplateReference
{
    public function __construct($bundle = null, $controller = null, $name = null, $format = null, $engine = null)
    {
        $this->parameters = array(
            'bundle'     => $bundle,
            'controller' => $controller,
            'name'       => $name,
            'format'     => $format,
            'engine'     => $engine,
        );
    }

    
    public function getPath()
    {
        $controller = str_replace('\\', '/', $this->get('controller'));

        $path = (empty($controller) ? '' : $controller.'/').$this->get('name').'.'.$this->get('format').'.'.$this->get('engine');

        return empty($this->parameters['bundle']) ? 'views/'.$path : '@'.$this->get('bundle').'/Resources/views/'.$path;
    }

    
    public function getLogicalName()
    {
        return sprintf('%s:%s:%s.%s.%s', $this->parameters['bundle'], $this->parameters['controller'], $this->parameters['name'], $this->parameters['format'], $this->parameters['engine']);
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Templating
{

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Component\HttpKernel\KernelInterface;


class TemplateNameParser implements TemplateNameParserInterface
{
    protected $kernel;
    protected $cache = array();

    
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    
    public function parse($name)
    {
        if ($name instanceof TemplateReferenceInterface) {
            return $name;
        } elseif (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

                $name = str_replace(':/', ':', preg_replace('#/{2,}#', '/', strtr($name, '\\', '/')));

        if (false !== strpos($name, '..')) {
            throw new \RuntimeException(sprintf('Template name "%s" contains invalid characters.', $name));
        }

        if (!preg_match('/^([^:]*):([^:]*):(.+)\.([^\.]+)\.([^\.]+)$/', $name, $matches)) {
            throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid (format is "bundle:section:template.format.engine").', $name));
        }

        $template = new TemplateReference($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);

        if ($template->get('bundle')) {
            try {
                $this->kernel->getBundle($template->get('bundle'));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(sprintf('Template name "%s" is not valid.', $name), 0, $e);
            }
        }

        return $this->cache[$name] = $template;
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Templating\Loader
{

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;


class TemplateLocator implements FileLocatorInterface
{
    protected $locator;
    protected $cache;

    
    public function __construct(FileLocatorInterface $locator, $cacheDir = null)
    {
        if (null !== $cacheDir && is_file($cache = $cacheDir.'/templates.php')) {
            $this->cache = require $cache;
        }

        $this->locator = $locator;
    }

    
    protected function getCacheKey($template)
    {
        return $template->getLogicalName();
    }

    
    public function locate($template, $currentPath = null, $first = true)
    {
        if (!$template instanceof TemplateReferenceInterface) {
            throw new \InvalidArgumentException('The template must be an instance of TemplateReferenceInterface.');
        }

        $key = $this->getCacheKey($template);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            return $this->cache[$key] = $this->locator->locate($template->getPath(), $currentPath);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Unable to find template "%s" : "%s".', $template, $e->getMessage()), 0, $e);
        }
    }
}
}
 



namespace Symfony\Component\Routing\Generator
{

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Psr\Log\LoggerInterface;


class UrlGenerator implements UrlGeneratorInterface, ConfigurableRequirementsInterface
{
    
    protected $routes;

    
    protected $context;

    
    protected $strictRequirements = true;

    
    protected $logger;

    
    protected $decodedChars = array(
                                '%2F' => '/',
                        '%40' => '@',
        '%3A' => ':',
                        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
    );

    
    public function __construct(RouteCollection $routes, RequestContext $context, LoggerInterface $logger = null)
    {
        $this->routes = $routes;
        $this->context = $context;
        $this->logger = $logger;
    }

    
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }

    
    public function getContext()
    {
        return $this->context;
    }

    
    public function setStrictRequirements($enabled)
    {
        $this->strictRequirements = null === $enabled ? null : (Boolean) $enabled;
    }

    
    public function isStrictRequirements()
    {
        return $this->strictRequirements;
    }

    
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        if (null === $route = $this->routes->get($name)) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
        }

                $compiledRoute = $route->compile();

        return $this->doGenerate($compiledRoute->getVariables(), $route->getDefaults(), $route->getRequirements(), $compiledRoute->getTokens(), $parameters, $name, $referenceType, $compiledRoute->getHostTokens());
    }

    
    protected function doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $referenceType, $hostTokens)
    {
        $variables = array_flip($variables);
        $mergedParams = array_replace($defaults, $this->context->getParameters(), $parameters);

                if ($diff = array_diff_key($variables, $mergedParams)) {
            throw new MissingMandatoryParametersException(sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route "%s".', implode('", "', array_keys($diff)), $name));
        }

        $url = '';
        $optional = true;
        foreach ($tokens as $token) {
            if ('variable' === $token[0]) {
                if (!$optional || !array_key_exists($token[3], $defaults) || null !== $mergedParams[$token[3]] && (string) $mergedParams[$token[3]] !== (string) $defaults[$token[3]]) {
                                        if (null !== $this->strictRequirements && !preg_match('#^'.$token[2].'$#', $mergedParams[$token[3]])) {
                        $message = sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given) to generate a corresponding URL.', $token[3], $name, $token[2], $mergedParams[$token[3]]);
                        if ($this->strictRequirements) {
                            throw new InvalidParameterException($message);
                        }

                        if ($this->logger) {
                            $this->logger->error($message);
                        }

                        return null;
                    }

                    $url = $token[1].$mergedParams[$token[3]].$url;
                    $optional = false;
                }
            } else {
                                $url = $token[1].$url;
                $optional = false;
            }
        }

        if ('' === $url) {
            $url = '/';
        }

                $url = strtr(rawurlencode($url), $this->decodedChars);

                                $url = strtr($url, array('/../' => '/%2E%2E/', '/./' => '/%2E/'));
        if ('/..' === substr($url, -3)) {
            $url = substr($url, 0, -2).'%2E%2E';
        } elseif ('/.' === substr($url, -2)) {
            $url = substr($url, 0, -1).'%2E';
        }

        $schemeAuthority = '';
        if ($host = $this->context->getHost()) {
            $scheme = $this->context->getScheme();
            if (isset($requirements['_scheme']) && ($req = strtolower($requirements['_scheme'])) && $scheme !== $req) {
                $referenceType = self::ABSOLUTE_URL;
                $scheme = $req;
            }

            if ($hostTokens) {
                $routeHost = '';
                foreach ($hostTokens as $token) {
                    if ('variable' === $token[0]) {
                        if (null !== $this->strictRequirements && !preg_match('#^'.$token[2].'$#', $mergedParams[$token[3]])) {
                            $message = sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given) to generate a corresponding URL.', $token[3], $name, $token[2], $mergedParams[$token[3]]);

                            if ($this->strictRequirements) {
                                throw new InvalidParameterException($message);
                            }

                            if ($this->logger) {
                                $this->logger->error($message);
                            }

                            return null;
                        }

                        $routeHost = $token[1].$mergedParams[$token[3]].$routeHost;
                    } else {
                        $routeHost = $token[1].$routeHost;
                    }
                }

                if ($routeHost !== $host) {
                    $host = $routeHost;
                    if (self::ABSOLUTE_URL !== $referenceType) {
                        $referenceType = self::NETWORK_PATH;
                    }
                }
            }

            if (self::ABSOLUTE_URL === $referenceType || self::NETWORK_PATH === $referenceType) {
                $port = '';
                if ('http' === $scheme && 80 != $this->context->getHttpPort()) {
                    $port = ':'.$this->context->getHttpPort();
                } elseif ('https' === $scheme && 443 != $this->context->getHttpsPort()) {
                    $port = ':'.$this->context->getHttpsPort();
                }

                $schemeAuthority = self::NETWORK_PATH === $referenceType ? '//' : "$scheme://";
                $schemeAuthority .= $host.$port;
            }
        }

        if (self::RELATIVE_PATH === $referenceType) {
            $url = self::getRelativePath($this->context->getPathInfo(), $url);
        } else {
            $url = $schemeAuthority.$this->context->getBaseUrl().$url;
        }

                $extra = array_diff_key($parameters, $variables, $defaults);
        if ($extra && $query = http_build_query($extra, '', '&')) {
            $url .= '?'.$query;
        }

        return $url;
    }

    
    public static function getRelativePath($basePath, $targetPath)
    {
        if ($basePath === $targetPath) {
            return '';
        }

        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

                                        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }
}
}
 



namespace Symfony\Component\Routing
{

use Symfony\Component\HttpFoundation\Request;


class RequestContext
{
    private $baseUrl;
    private $pathInfo;
    private $method;
    private $host;
    private $scheme;
    private $httpPort;
    private $httpsPort;
    private $queryString;

    
    private $parameters = array();

    
    public function __construct($baseUrl = '', $method = 'GET', $host = 'localhost', $scheme = 'http', $httpPort = 80, $httpsPort = 443, $path = '/', $queryString = '')
    {
        $this->baseUrl = $baseUrl;
        $this->method = strtoupper($method);
        $this->host = $host;
        $this->scheme = strtolower($scheme);
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
        $this->pathInfo = $path;
        $this->queryString = $queryString;
    }

    public function fromRequest(Request $request)
    {
        $this->setBaseUrl($request->getBaseUrl());
        $this->setPathInfo($request->getPathInfo());
        $this->setMethod($request->getMethod());
        $this->setHost($request->getHost());
        $this->setScheme($request->getScheme());
        $this->setHttpPort($request->isSecure() ? $this->httpPort : $request->getPort());
        $this->setHttpsPort($request->isSecure() ? $request->getPort() : $this->httpsPort);
        $this->setQueryString($request->server->get('QUERY_STRING'));
    }

    
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    
    public function getPathInfo()
    {
        return $this->pathInfo;
    }

    
    public function setPathInfo($pathInfo)
    {
        $this->pathInfo = $pathInfo;
    }

    
    public function getMethod()
    {
        return $this->method;
    }

    
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    
    public function getHost()
    {
        return $this->host;
    }

    
    public function setHost($host)
    {
        $this->host = $host;
    }

    
    public function getScheme()
    {
        return $this->scheme;
    }

    
    public function setScheme($scheme)
    {
        $this->scheme = strtolower($scheme);
    }

    
    public function getHttpPort()
    {
        return $this->httpPort;
    }

    
    public function setHttpPort($httpPort)
    {
        $this->httpPort = $httpPort;
    }

    
    public function getHttpsPort()
    {
        return $this->httpsPort;
    }

    
    public function setHttpsPort($httpsPort)
    {
        $this->httpsPort = $httpsPort;
    }

    
    public function getQueryString()
    {
        return $this->queryString;
    }

    
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    
    public function getParameters()
    {
        return $this->parameters;
    }

    
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    
    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    
    public function hasParameter($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    
    public function setParameter($name, $parameter)
    {
        $this->parameters[$name] = $parameter;
    }
}
}
 



namespace Symfony\Component\Routing
{

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\ConfigCache;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\ConfigurableRequirementsInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Generator\Dumper\GeneratorDumperInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\Dumper\MatcherDumperInterface;
use Symfony\Component\HttpFoundation\Request;


class Router implements RouterInterface, RequestMatcherInterface
{
    
    protected $matcher;

    
    protected $generator;

    
    protected $context;

    
    protected $loader;

    
    protected $collection;

    
    protected $resource;

    
    protected $options = array();

    
    protected $logger;

    
    public function __construct(LoaderInterface $loader, $resource, array $options = array(), RequestContext $context = null, LoggerInterface $logger = null)
    {
        $this->loader = $loader;
        $this->resource = $resource;
        $this->logger = $logger;
        $this->context = $context ?: new RequestContext();
        $this->setOptions($options);
    }

    
    public function setOptions(array $options)
    {
        $this->options = array(
            'cache_dir'              => null,
            'debug'                  => false,
            'generator_class'        => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_base_class'   => 'Symfony\\Component\\Routing\\Generator\\UrlGenerator',
            'generator_dumper_class' => 'Symfony\\Component\\Routing\\Generator\\Dumper\\PhpGeneratorDumper',
            'generator_cache_class'  => 'ProjectUrlGenerator',
            'matcher_class'          => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_base_class'     => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
            'matcher_dumper_class'   => 'Symfony\\Component\\Routing\\Matcher\\Dumper\\PhpMatcherDumper',
            'matcher_cache_class'    => 'ProjectUrlMatcher',
            'resource_type'          => null,
            'strict_requirements'    => true,
        );

                $invalid = array();
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            } else {
                $invalid[] = $key;
            }
        }

        if ($invalid) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the following options: "%s".', implode('", "', $invalid)));
        }
    }

    
    public function setOption($key, $value)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
        }

        $this->options[$key] = $value;
    }

    
    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The Router does not support the "%s" option.', $key));
        }

        return $this->options[$key];
    }

    
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->loader->load($this->resource, $this->options['resource_type']);
        }

        return $this->collection;
    }

    
    public function setContext(RequestContext $context)
    {
        $this->context = $context;

        if (null !== $this->matcher) {
            $this->getMatcher()->setContext($context);
        }
        if (null !== $this->generator) {
            $this->getGenerator()->setContext($context);
        }
    }

    
    public function getContext()
    {
        return $this->context;
    }

    
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        return $this->getGenerator()->generate($name, $parameters, $referenceType);
    }

    
    public function match($pathinfo)
    {
        return $this->getMatcher()->match($pathinfo);
    }

    
    public function matchRequest(Request $request)
    {
        $matcher = $this->getMatcher();
        if (!$matcher instanceof RequestMatcherInterface) {
                        return $matcher->match($request->getPathInfo());
        }

        return $matcher->matchRequest($request);
    }

    
    public function getMatcher()
    {
        if (null !== $this->matcher) {
            return $this->matcher;
        }

        if (null === $this->options['cache_dir'] || null === $this->options['matcher_cache_class']) {
            return $this->matcher = new $this->options['matcher_class']($this->getRouteCollection(), $this->context);
        }

        $class = $this->options['matcher_cache_class'];
        $cache = new ConfigCache($this->options['cache_dir'].'/'.$class.'.php', $this->options['debug']);
        if (!$cache->isFresh()) {
            $dumper = $this->getMatcherDumperInstance();

            $options = array(
                'class'      => $class,
                'base_class' => $this->options['matcher_base_class'],
            );

            $cache->write($dumper->dump($options), $this->getRouteCollection()->getResources());
        }

        require_once $cache;

        return $this->matcher = new $class($this->context);
    }

    
    public function getGenerator()
    {
        if (null !== $this->generator) {
            return $this->generator;
        }

        if (null === $this->options['cache_dir'] || null === $this->options['generator_cache_class']) {
            $this->generator = new $this->options['generator_class']($this->getRouteCollection(), $this->context, $this->logger);
        } else {
            $class = $this->options['generator_cache_class'];
            $cache = new ConfigCache($this->options['cache_dir'].'/'.$class.'.php', $this->options['debug']);
            if (!$cache->isFresh()) {
                $dumper = $this->getGeneratorDumperInstance();

                $options = array(
                    'class'      => $class,
                    'base_class' => $this->options['generator_base_class'],
                );

                $cache->write($dumper->dump($options), $this->getRouteCollection()->getResources());
            }

            require_once $cache;

            $this->generator = new $class($this->context, $this->logger);
        }

        if ($this->generator instanceof ConfigurableRequirementsInterface) {
            $this->generator->setStrictRequirements($this->options['strict_requirements']);
        }

        return $this->generator;
    }

    
    protected function getGeneratorDumperInstance()
    {
        return new $this->options['generator_dumper_class']($this->getRouteCollection());
    }

    
    protected function getMatcherDumperInstance()
    {
        return new $this->options['matcher_dumper_class']($this->getRouteCollection());
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Routing
{

use Symfony\Component\Routing\Matcher\RedirectableUrlMatcher as BaseMatcher;


class RedirectableUrlMatcher extends BaseMatcher
{
    
    public function redirect($path, $route, $scheme = null)
    {
        return array(
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            'path'        => $path,
            'permanent'   => true,
            'scheme'      => $scheme,
            'httpPort'    => $this->context->getHttpPort(),
            'httpsPort'   => $this->context->getHttpsPort(),
            '_route'      => $route,
        );
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Routing
{

use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;


class Router extends BaseRouter implements WarmableInterface
{
    private $container;

    
    public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null)
    {
        $this->container = $container;

        $this->resource = $resource;
        $this->context = $context ?: new RequestContext();
        $this->setOptions($options);
    }

    
    public function getRouteCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
            $this->resolveParameters($this->collection);
        }

        return $this->collection;
    }

    
    public function warmUp($cacheDir)
    {
        $currentDir = $this->getOption('cache_dir');

                $this->setOption('cache_dir', $cacheDir);
        $this->getMatcher();
        $this->getGenerator();

        $this->setOption('cache_dir', $currentDir);
    }

    
    private function resolveParameters(RouteCollection $collection)
    {
        foreach ($collection as $route) {
            foreach ($route->getDefaults() as $name => $value) {
                $route->setDefault($name, $this->resolve($value));
            }

            foreach ($route->getRequirements() as $name => $value) {
                 $route->setRequirement($name, $this->resolve($value));
            }

            $route->setPath($this->resolve($route->getPath()));
            $route->setHost($this->resolve($route->getHost()));
        }
    }

    
    private function resolve($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->resolve($val);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $container = $this->container;

        $escapedValue = preg_replace_callback('/%%|%([^%\s]++)%/', function ($match) use ($container, $value) {
                        if (!isset($match[1])) {
                return '%%';
            }

            $resolved = $container->getParameter($match[1]);

            if (is_string($resolved) || is_numeric($resolved)) {
                return (string) $resolved;
            }

            throw new RuntimeException(sprintf(
                'The container parameter "%s", used in the route configuration value "%s", ' .
                'must be a string or numeric, but it is of type %s.',
                $match[1],
                $value,
                gettype($resolved)
                )
            );

        }, $value);

        return str_replace('%%', '%', $escapedValue);
    }
}
}
 



namespace Symfony\Component\Config
{


class FileLocator implements FileLocatorInterface
{
    protected $paths;

    
    public function __construct($paths = array())
    {
        $this->paths = (array) $paths;
    }

    
    public function locate($name, $currentPath = null, $first = true)
    {
        if ($this->isAbsolutePath($name)) {
            if (!file_exists($name)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $name));
            }

            return $name;
        }

        $filepaths = array();
        if (null !== $currentPath && file_exists($file = $currentPath.DIRECTORY_SEPARATOR.$name)) {
            if (true === $first) {
                return $file;
            }
            $filepaths[] = $file;
        }

        foreach ($this->paths as $path) {
            if (file_exists($file = $path.DIRECTORY_SEPARATOR.$name)) {
                if (true === $first) {
                    return $file;
                }
                $filepaths[] = $file;
            }
        }

        if (!$filepaths) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s%s).', $name, null !== $currentPath ? $currentPath.', ' : '', implode(', ', $this->paths)));
        }

        return array_values(array_unique($filepaths));
    }

    
    private function isAbsolutePath($file)
    {
        if ($file[0] == '/' || $file[0] == '\\'
            || (strlen($file) > 3 && ctype_alpha($file[0])
                && $file[1] == ':'
                && ($file[2] == '\\' || $file[2] == '/')
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }
}
}
 



namespace Symfony\Component\EventDispatcher
{


class Event
{
    
    private $propagationStopped = false;

    
    private $dispatcher;

    
    private $name;

    
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }

    
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    
    public function getName()
    {
        return $this->name;
    }

    
    public function setName($name)
    {
        $this->name = $name;
    }
}
}
 



namespace Symfony\Component\EventDispatcher
{

use Symfony\Component\DependencyInjection\ContainerInterface;


class ContainerAwareEventDispatcher extends EventDispatcher
{
    
    private $container;

    
    private $listenerIds = array();

    
    private $listeners = array();

    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
    public function addListenerService($eventName, $callback, $priority = 0)
    {
        if (!is_array($callback) || 2 !== count($callback)) {
            throw new \InvalidArgumentException('Expected an array("service", "method") argument');
        }

        $this->listenerIds[$eventName][] = array($callback[0], $callback[1], $priority);
    }

    public function removeListener($eventName, $listener)
    {
        $this->lazyLoad($eventName);

        if (isset($this->listeners[$eventName])) {
            foreach ($this->listeners[$eventName] as $key => $l) {
                foreach ($this->listenerIds[$eventName] as $i => $args) {
                    list($serviceId, $method, $priority) = $args;
                    if ($key === $serviceId.'.'.$method) {
                        if ($listener === array($l, $method)) {
                            unset($this->listeners[$eventName][$key]);
                            if (empty($this->listeners[$eventName])) {
                                unset($this->listeners[$eventName]);
                            }
                            unset($this->listenerIds[$eventName][$i]);
                            if (empty($this->listenerIds[$eventName])) {
                                unset($this->listenerIds[$eventName]);
                            }
                        }
                    }
                }
            }
        }

        parent::removeListener($eventName, $listener);
    }

    
    public function hasListeners($eventName = null)
    {
        if (null === $eventName) {
            return (Boolean) count($this->listenerIds) || (Boolean) count($this->listeners);
        }

        if (isset($this->listenerIds[$eventName])) {
            return true;
        }

        return parent::hasListeners($eventName);
    }

    
    public function getListeners($eventName = null)
    {
        if (null === $eventName) {
            foreach (array_keys($this->listenerIds) as $serviceEventName) {
                $this->lazyLoad($serviceEventName);
            }
        } else {
            $this->lazyLoad($eventName);
        }

        return parent::getListeners($eventName);
    }

    
    public function addSubscriberService($serviceId, $class)
    {
        foreach ($class::getSubscribedEvents() as $eventName => $params) {
            if (is_string($params)) {
                $this->listenerIds[$eventName][] = array($serviceId, $params, 0);
            } elseif (is_string($params[0])) {
                $this->listenerIds[$eventName][] = array($serviceId, $params[0], isset($params[1]) ? $params[1] : 0);
            } else {
                foreach ($params as $listener) {
                    $this->listenerIds[$eventName][] = array($serviceId, $listener[0], isset($listener[1]) ? $listener[1] : 0);
                }
            }
        }
    }

    
    public function dispatch($eventName, Event $event = null)
    {
        $this->lazyLoad($eventName);

        return parent::dispatch($eventName, $event);
    }

    public function getContainer()
    {
        return $this->container;
    }

    
    protected function lazyLoad($eventName)
    {
        if (isset($this->listenerIds[$eventName])) {
            foreach ($this->listenerIds[$eventName] as $args) {
                list($serviceId, $method, $priority) = $args;
                $listener = $this->container->get($serviceId);

                $key = $serviceId.'.'.$method;
                if (!isset($this->listeners[$eventName][$key])) {
                    $this->addListener($eventName, array($listener, $method), $priority);
                } elseif ($listener !== $this->listeners[$eventName][$key]) {
                    parent::removeListener($eventName, array($this->listeners[$eventName][$key], $method));
                    $this->addListener($eventName, array($listener, $method), $priority);
                }

                $this->listeners[$eventName][$key] = $listener;
            }
        }
    }
}
}
 



namespace Symfony\Component\HttpKernel\EventListener
{

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class ResponseListener implements EventSubscriberInterface
{
    private $charset;

    public function __construct($charset)
    {
        $this->charset = $charset;
    }

    
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (null === $response->getCharset()) {
            $response->setCharset($this->charset);
        }

        $response->prepare($event->getRequest());
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }
}
}
 



namespace Symfony\Component\HttpKernel\EventListener
{

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;


class RouterListener implements EventSubscriberInterface
{
    private $matcher;
    private $context;
    private $logger;
    private $request;
    private $requestStack;

    
    public function __construct($matcher, RequestContext $context = null, LoggerInterface $logger = null, RequestStack $requestStack = null)
    {
        if (!$matcher instanceof UrlMatcherInterface && !$matcher instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
        }

        if (null === $context && !$matcher instanceof RequestContextAwareInterface) {
            throw new \InvalidArgumentException('You must either pass a RequestContext or the matcher must implement RequestContextAwareInterface.');
        }

        $this->matcher = $matcher;
        $this->context = $context ?: $matcher->getContext();
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    
    public function setRequest(Request $request = null)
    {
        if (null !== $request && $this->request !== $request) {
            $this->context->fromRequest($request);
        }
        $this->request = $request;
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        if (null === $this->requestStack) {
            return;         }

        $this->setRequest($this->requestStack->getParentRequest());
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

                                        if (null !== $this->requestStack) {
            $this->setRequest($request);
        }

        if ($request->attributes->has('_controller')) {
                        return;
        }

                try {
                        if ($this->matcher instanceof RequestMatcherInterface) {
                $parameters = $this->matcher->matchRequest($request);
            } else {
                $parameters = $this->matcher->match($request->getPathInfo());
            }

            if (null !== $this->logger) {
                $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], $this->parametersToString($parameters)));
            }

            $request->attributes->add($parameters);
            unset($parameters['_route']);
            unset($parameters['_controller']);
            $request->attributes->set('_route_params', $parameters);
        } catch (ResourceNotFoundException $e) {
            $message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());

            if ($referer = $request->headers->get('referer')) {
                $message .= sprintf(' (from "%s")', $referer);
            }

            throw new NotFoundHttpException($message, $e);
        } catch (MethodNotAllowedException $e) {
            $message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $e->getAllowedMethods()));

            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
        }
    }

    private function parametersToString(array $parameters)
    {
        $pieces = array();
        foreach ($parameters as $key => $val) {
            $pieces[] = sprintf('"%s": "%s"', $key, (is_string($val) ? $val : json_encode($val)));
        }

        return implode(', ', $pieces);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 32)),
            KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
        );
    }
}
}
 



namespace Symfony\Component\HttpKernel\Controller
{

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;


class ControllerResolver implements ControllerResolverInterface
{
    private $logger;

    
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    
    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            if (null !== $this->logger) {
                $this->logger->warning('Unable to look for the controller as the "_controller" parameter is missing');
            }

            return false;
        }

        if (is_array($controller) || (is_object($controller) && method_exists($controller, '__invoke'))) {
            return $controller;
        }

        if (false === strpos($controller, ':')) {
            if (method_exists($controller, '__invoke')) {
                return new $controller;
            } elseif (function_exists($controller)) {
                return $controller;
            }
        }

        $callable = $this->createController($controller);

        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(sprintf('The controller for URI "%s" is not callable.', $request->getPathInfo()));
        }

        return $callable;
    }

    
    public function getArguments(Request $request, $controller)
    {
        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new \ReflectionFunction($controller);
        }

        return $this->doGetArguments($request, $controller, $r->getParameters());
    }

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();
        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                $arguments[] = $attributes[$param->name];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
            }
        }

        return $arguments;
    }

    
    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            throw new \InvalidArgumentException(sprintf('Unable to find controller "%s".', $controller));
        }

        list($class, $method) = explode('::', $controller, 2);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        return array(new $class(), $method);
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;


class KernelEvent extends Event
{
    
    private $kernel;

    
    private $request;

    
    private $requestType;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType)
    {
        $this->kernel = $kernel;
        $this->request = $request;
        $this->requestType = $requestType;
    }

    
    public function getKernel()
    {
        return $this->kernel;
    }

    
    public function getRequest()
    {
        return $this->request;
    }

    
    public function getRequestType()
    {
        return $this->requestType;
    }

    
    public function isMasterRequest()
    {
        return HttpKernelInterface::MASTER_REQUEST === $this->requestType;
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;


class FilterControllerEvent extends KernelEvent
{
    
    private $controller;

    public function __construct(HttpKernelInterface $kernel, $controller, Request $request, $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setController($controller);
    }

    
    public function getController()
    {
        return $this->controller;
    }

    
    public function setController($controller)
    {
                if (!is_callable($controller)) {
            throw new \LogicException(sprintf('The controller must be a callable (%s given).', $this->varToString($controller)));
        }

        $this->controller = $controller;
    }

    private function varToString($var)
    {
        if (is_object($var)) {
            return sprintf('Object(%s)', get_class($var));
        }

        if (is_array($var)) {
            $a = array();
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => %s', $k, $this->varToString($v));
            }

            return sprintf("Array(%s)", implode(', ', $a));
        }

        if (is_resource($var)) {
            return sprintf('Resource(%s)', get_resource_type($var));
        }

        if (null === $var) {
            return 'null';
        }

        if (false === $var) {
            return 'false';
        }

        if (true === $var) {
            return 'true';
        }

        return (string) $var;
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class FilterResponseEvent extends KernelEvent
{
    
    private $response;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, Response $response)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setResponse($response);
    }

    
    public function getResponse()
    {
        return $this->response;
    }

    
    public function setResponse(Response $response)
    {
        $this->response = $response;
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpFoundation\Response;


class GetResponseEvent extends KernelEvent
{
    
    private $response;

    
    public function getResponse()
    {
        return $this->response;
    }

    
    public function setResponse(Response $response)
    {
        $this->response = $response;

        $this->stopPropagation();
    }

    
    public function hasResponse()
    {
        return null !== $this->response;
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;


class GetResponseForControllerResultEvent extends GetResponseEvent
{
    
    private $controllerResult;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, $controllerResult)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->controllerResult = $controllerResult;
    }

    
    public function getControllerResult()
    {
        return $this->controllerResult;
    }

    
    public function setControllerResult($controllerResult)
    {
        $this->controllerResult = $controllerResult;
    }
}
}
 



namespace Symfony\Component\HttpKernel\Event
{

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;


class GetResponseForExceptionEvent extends GetResponseEvent
{
    
    private $exception;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, \Exception $e)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setException($e);
    }

    
    public function getException()
    {
        return $this->exception;
    }

    
    public function setException(\Exception $exception)
    {
        $this->exception = $exception;
    }
}
}
 



namespace Symfony\Component\HttpKernel
{


final class KernelEvents
{
    
    const REQUEST = 'kernel.request';

    
    const EXCEPTION = 'kernel.exception';

    
    const VIEW = 'kernel.view';

    
    const CONTROLLER = 'kernel.controller';

    
    const RESPONSE = 'kernel.response';

    
    const TERMINATE = 'kernel.terminate';

    
    const FINISH_REQUEST = 'kernel.finish_request';
}
}
 



namespace Symfony\Component\HttpKernel\Config
{

use Symfony\Component\Config\FileLocator as BaseFileLocator;
use Symfony\Component\HttpKernel\KernelInterface;


class FileLocator extends BaseFileLocator
{
    private $kernel;
    private $path;

    
    public function __construct(KernelInterface $kernel, $path = null, array $paths = array())
    {
        $this->kernel = $kernel;
        if (null !== $path) {
            $this->path = $path;
            $paths[] = $path;
        }

        parent::__construct($paths);
    }

    
    public function locate($file, $currentPath = null, $first = true)
    {
        if ('@' === $file[0]) {
            return $this->kernel->locateResource($file, $this->path, $first);
        }

        return parent::locate($file, $currentPath, $first);
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Controller
{

use Symfony\Component\HttpKernel\KernelInterface;


class ControllerNameParser
{
    protected $kernel;

    
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    
    public function parse($controller)
    {
        if (3 != count($parts = explode(':', $controller))) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "a:b:c" controller string.', $controller));
        }

        list($bundle, $controller, $action) = $parts;
        $controller = str_replace('/', '\\', $controller);
        $bundles = array();

                foreach ($this->kernel->getBundle($bundle, false) as $b) {
            $try = $b->getNamespace().'\\Controller\\'.$controller.'Controller';
            if (class_exists($try)) {
                return $try.'::'.$action.'Action';
            }

            $bundles[] = $b->getName();
            $msg = sprintf('Unable to find controller "%s:%s" - class "%s" does not exist.', $bundle, $controller, $try);
        }

        if (count($bundles) > 1) {
            $msg = sprintf('Unable to find controller "%s:%s" in bundles %s.', $bundle, $controller, implode(', ', $bundles));
        }

        throw new \InvalidArgumentException($msg);
    }

    
    public function build($controller)
    {
        if (0 === preg_match('#^(.*?\\\\Controller\\\\(.+)Controller)::(.+)Action$#', $controller, $match)) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "class::method" string.', $controller));
        }

        $className = $match[1];
        $controllerName = $match[2];
        $actionName = $match[3];
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            if (0 !== strpos($className, $bundle->getNamespace())) {
                continue;
            }

            return sprintf('%s:%s:%s', $name, $controllerName, $actionName);
        }

        throw new \InvalidArgumentException(sprintf('Unable to find a bundle that defines controller "%s".', $controller));
    }
}
}
 



namespace Symfony\Bundle\FrameworkBundle\Controller
{

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;


class ControllerResolver extends BaseControllerResolver
{
    protected $container;
    protected $parser;
    protected static $container_carrier; 
    
    public function __construct(ContainerInterface $container, ControllerNameParser $parser, LoggerInterface $logger = null)
    {
        $this->container = $container;
        self::$container_carrier = $container;
        $this->parser = $parser;        
        parent::__construct($logger);
    }
    
    
    public static function getContainer(){
        return self::$container_carrier;
    }

    
    protected function createController($controller)
    {
        
        if (false === strpos($controller, '::')) {
            $count = substr_count($controller, ':');
            if (2 == $count) {
                                $controller = $this->parser->parse($controller);
            } elseif (1 == $count) {
                                list($service, $method) = explode(':', $controller, 2);

                return array($this->container->get($service), $method);
            } else {
                throw new \LogicException(sprintf('Unable to parse the controller name "%s".', $controller));
            }
        }

        list($class, $method) = explode('::', $controller, 2);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }
        
        
        $controller = new $class();
        if ($controller instanceof ContainerAwareInterface) {          
            $controller->setContainer($this->container);
        }

        return array($controller, $method);
    }
}
}
 



namespace Symfony\Component\Security\Http
{

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class Firewall implements EventSubscriberInterface
{
    private $map;
    private $dispatcher;
    private $exceptionListeners;

    
    public function __construct(FirewallMapInterface $map, EventDispatcherInterface $dispatcher)
    {
        $this->map = $map;
        $this->dispatcher = $dispatcher;
        $this->exceptionListeners = new \SplObjectStorage();
    }

    
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

                list($listeners, $exception) = $this->map->getListeners($event->getRequest());
        if (null !== $exception) {
            $this->exceptionListeners[$event->getRequest()] = $exception;
            $exception->register($this->dispatcher);
        }

                foreach ($listeners as $listener) {
            $listener->handle($event);

            if ($event->hasResponse()) {
                break;
            }
        }
    }

    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $request = $event->getRequest();

        if (isset($this->exceptionListeners[$request])) {
            $this->exceptionListeners[$request]->unregister($this->dispatcher);
            unset($this->exceptionListeners[$request]);
        }
    }

    
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 8),
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
        );
    }
}
}
 



namespace Symfony\Component\Security\Core
{

use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


class SecurityContext implements SecurityContextInterface
{
    private $token;
    private $accessDecisionManager;
    private $authenticationManager;
    private $alwaysAuthenticate;

    
    public function __construct(AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager, $alwaysAuthenticate = false)
    {
        $this->authenticationManager = $authenticationManager;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->alwaysAuthenticate = $alwaysAuthenticate;
    }

    
    final public function isGranted($attributes, $object = null)
    {
        if (null === $this->token) {
            throw new AuthenticationCredentialsNotFoundException('The security context contains no authentication token. One possible reason may be that there is no firewall configured for this URL.');
        }

        if ($this->alwaysAuthenticate || !$this->token->isAuthenticated()) {
            $this->token = $this->authenticationManager->authenticate($this->token);
        }

        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }

        return $this->accessDecisionManager->decide($this->token, $attributes, $object);
    }

    
    public function getToken()
    {
        return $this->token;
    }

    
    public function setToken(TokenInterface $token = null)
    {
        $this->token = $token;
    }
}
}
 



namespace Symfony\Component\Security\Core\User
{

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;


interface UserProviderInterface
{
    
    public function loadUserByUsername($username);

    
    public function refreshUser(UserInterface $user);

    
    public function supportsClass($class);
}
}
 



namespace Symfony\Component\Security\Core\Authentication
{

use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


class AuthenticationProviderManager implements AuthenticationManagerInterface
{
    private $providers;
    private $eraseCredentials;
    private $eventDispatcher;

    
    public function __construct(array $providers, $eraseCredentials = true)
    {
        if (!$providers) {
            throw new \InvalidArgumentException('You must at least add one authentication provider.');
        }

        $this->providers = $providers;
        $this->eraseCredentials = (Boolean) $eraseCredentials;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    
    public function authenticate(TokenInterface $token)
    {
        $lastException = null;
        $result = null;

        foreach ($this->providers as $provider) {
            if (!$provider->supports($token)) {
                continue;
            }

            try {
                $result = $provider->authenticate($token);

                if (null !== $result) {
                    break;
                }
            } catch (AccountStatusException $e) {
                $e->setToken($token);

                throw $e;
            } catch (AuthenticationException $e) {
                $lastException = $e;
            }
        }

        if (null !== $result) {
            if (true === $this->eraseCredentials) {
                $result->eraseCredentials();
            }

            if (null !== $this->eventDispatcher) {
                $this->eventDispatcher->dispatch(AuthenticationEvents::AUTHENTICATION_SUCCESS, new AuthenticationEvent($result));
            }

            return $result;
        }

        if (null === $lastException) {
            $lastException = new ProviderNotFoundException(sprintf('No Authentication Provider found for token of class "%s".', get_class($token)));
        }

        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(AuthenticationEvents::AUTHENTICATION_FAILURE, new AuthenticationFailureEvent($token, $lastException));
        }

        $lastException->setToken($token);

        throw $lastException;
    }
}
}
 



namespace Symfony\Component\Security\Core\Authorization
{

use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


class AccessDecisionManager implements AccessDecisionManagerInterface
{
    private $voters;
    private $strategy;
    private $allowIfAllAbstainDecisions;
    private $allowIfEqualGrantedDeniedDecisions;

    
    public function __construct(array $voters, $strategy = 'affirmative', $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
    {
        if (!$voters) {
            throw new \InvalidArgumentException('You must at least add one voter.');
        }

        $strategyMethod = 'decide'.ucfirst($strategy);
        if (!is_callable(array($this, $strategyMethod))) {
            throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategy));
        }

        $this->voters = $voters;
        $this->strategy = $strategyMethod;
        $this->allowIfAllAbstainDecisions = (Boolean) $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = (Boolean) $allowIfEqualGrantedDeniedDecisions;
    }

    
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        return $this->{$this->strategy}($token, $attributes, $object);
    }

    
    public function supportsAttribute($attribute)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }

    
    public function supportsClass($class)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }

    
    private function decideAffirmative(TokenInterface $token, array $attributes, $object = null)
    {
        $deny = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);
            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    return true;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;

                default:
                    break;
            }
        }

        if ($deny > 0) {
            return false;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    
    private function decideConsensus(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        $deny = 0;
        $abstain = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);

            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    ++$grant;

                    break;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;

                default:
                    ++$abstain;

                    break;
            }
        }

        if ($grant > $deny) {
            return true;
        }

        if ($deny > $grant) {
            return false;
        }

        if ($grant == $deny && $grant != 0) {
            return $this->allowIfEqualGrantedDeniedDecisions;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    
    private function decideUnanimous(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        foreach ($attributes as $attribute) {
            foreach ($this->voters as $voter) {
                $result = $voter->vote($token, $object, array($attribute));

                switch ($result) {
                    case VoterInterface::ACCESS_GRANTED:
                        ++$grant;

                        break;

                    case VoterInterface::ACCESS_DENIED:
                        return false;

                    default:
                        break;
                }
            }
        }

                if ($grant > 0) {
            return true;
        }

        return $this->allowIfAllAbstainDecisions;
    }
}
}
 



namespace Symfony\Component\Security\Core\Authorization\Voter
{

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;


interface VoterInterface
{
    const ACCESS_GRANTED = 1;
    const ACCESS_ABSTAIN = 0;
    const ACCESS_DENIED  = -1;

    
    public function supportsAttribute($attribute);

    
    public function supportsClass($class);

    
    public function vote(TokenInterface $token, $object, array $attributes);
}
}
 



namespace Symfony\Bundle\SecurityBundle\Security
{

use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;


class FirewallMap implements FirewallMapInterface
{
    protected $container;
    protected $map;

    public function __construct(ContainerInterface $container, array $map)
    {
        $this->container = $container;
        $this->map = $map;
    }

    public function getListeners(Request $request)
    {
        foreach ($this->map as $contextId => $requestMatcher) {
            if (null === $requestMatcher || $requestMatcher->matches($request)) {
                return $this->container->get($contextId)->getContext();
            }
        }

        return array(array(), null);
    }
}
}
 



namespace Symfony\Bundle\SecurityBundle\Security
{

use Symfony\Component\Security\Http\Firewall\ExceptionListener;


class FirewallContext
{
    private $listeners;
    private $exceptionListener;

    public function __construct(array $listeners, ExceptionListener $exceptionListener = null)
    {
        $this->listeners = $listeners;
        $this->exceptionListener = $exceptionListener;
    }

    public function getContext()
    {
        return array($this->listeners, $this->exceptionListener);
    }
}
}
 



namespace Symfony\Component\HttpFoundation
{


class RequestMatcher implements RequestMatcherInterface
{
    
    private $path;

    
    private $host;

    
    private $methods = array();

    
    private $ips = array();

    
    private $attributes = array();

    
    public function __construct($path = null, $host = null, $methods = null, $ips = null, array $attributes = array())
    {
        $this->matchPath($path);
        $this->matchHost($host);
        $this->matchMethod($methods);
        $this->matchIps($ips);
        foreach ($attributes as $k => $v) {
            $this->matchAttribute($k, $v);
        }
    }

    
    public function matchHost($regexp)
    {
        $this->host = $regexp;
    }

    
    public function matchPath($regexp)
    {
        $this->path = $regexp;
    }

    
    public function matchIp($ip)
    {
        $this->matchIps($ip);
    }

    
    public function matchIps($ips)
    {
        $this->ips = (array) $ips;
    }

    
    public function matchMethod($method)
    {
        $this->methods = array_map('strtoupper', (array) $method);
    }

    
    public function matchAttribute($key, $regexp)
    {
        $this->attributes[$key] = $regexp;
    }

    
    public function matches(Request $request)
    {
        if ($this->methods && !in_array($request->getMethod(), $this->methods)) {
            return false;
        }

        foreach ($this->attributes as $key => $pattern) {
            if (!preg_match('{'.$pattern.'}', $request->attributes->get($key))) {
                return false;
            }
        }

        if (null !== $this->path && !preg_match('{'.$this->path.'}', rawurldecode($request->getPathInfo()))) {
            return false;
        }

        if (null !== $this->host && !preg_match('{'.$this->host.'}i', $request->getHost())) {
            return false;
        }

        if (IpUtils::checkIp($request->getClientIp(), $this->ips)) {
            return true;
        }

                        return count($this->ips) === 0;
    }
}
}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Stores the Twig configuration.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Environment
{
    const VERSION = '1.15.0';
    protected $charset;
    protected $loader;
    protected $debug;
    protected $autoReload;
    protected $cache;
    protected $lexer;
    protected $parser;
    protected $compiler;
    protected $baseTemplateClass;
    protected $extensions;
    protected $parsers;
    protected $visitors;
    protected $filters;
    protected $tests;
    protected $functions;
    protected $globals;
    protected $runtimeInitialized;
    protected $extensionInitialized;
    protected $loadedTemplates;
    protected $strictVariables;
    protected $unaryOperators;
    protected $binaryOperators;
    protected $templateClassPrefix = '__TwigTemplate_';
    protected $functionCallbacks;
    protected $filterCallbacks;
    protected $staging;
    /**
     * Constructor.
     *
     * Available options:
     *
     *  * debug: When set to true, it automatically set "auto_reload" to true as
     *           well (default to false).
     *
     *  * charset: The charset used by the templates (default to UTF-8).
     *
     *  * base_template_class: The base template class to use for generated
     *                         templates (default to Twig_Template).
     *
     *  * cache: An absolute path where to store the compiled templates, or
     *           false to disable compilation cache (default).
     *
     *  * auto_reload: Whether to reload the template if the original source changed.
     *                 If you don't provide the auto_reload option, it will be
     *                 determined automatically based on the debug value.
     *
     *  * strict_variables: Whether to ignore invalid variables in templates
     *                      (default to false).
     *
     *  * autoescape: Whether to enable auto-escaping (default to html):
     *                  * false: disable auto-escaping
     *                  * true: equivalent to html
     *                  * html, js: set the autoescaping to one of the supported strategies
     *                  * PHP callback: a PHP callback that returns an escaping strategy based on the template "filename"
     *
     *  * optimizations: A flag that indicates which optimizations to apply
     *                   (default to -1 which means that all optimizations are enabled;
     *                   set it to 0 to disable).
     *
     * @param Twig_LoaderInterface $loader  A Twig_LoaderInterface instance
     * @param array                $options An array of options
     */
    public function __construct(Twig_LoaderInterface $loader = null, $options = array())
    {
        if (null !== $loader) {
            $this->setLoader($loader);
        }
        $options = array_merge(array(
            'debug'               => false,
            'charset'             => 'UTF-8',
            'base_template_class' => 'Twig_Template',
            'strict_variables'    => false,
            'autoescape'          => 'html',
            'cache'               => false,
            'auto_reload'         => null,
            'optimizations'       => -1,
        ), $options);
        $this->debug              = (bool) $options['debug'];
        $this->charset            = strtoupper($options['charset']);
        $this->baseTemplateClass  = $options['base_template_class'];
        $this->autoReload         = null === $options['auto_reload'] ? $this->debug : (bool) $options['auto_reload'];
        $this->strictVariables    = (bool) $options['strict_variables'];
        $this->runtimeInitialized = false;
        $this->setCache($options['cache']);
        $this->functionCallbacks = array();
        $this->filterCallbacks = array();
        $this->addExtension(new Twig_Extension_Core());
        $this->addExtension(new Twig_Extension_Escaper($options['autoescape']));
        $this->addExtension(new Twig_Extension_Optimizer($options['optimizations']));
        $this->extensionInitialized = false;
        $this->staging = new Twig_Extension_Staging();
    }
    /**
     * Gets the base template class for compiled templates.
     *
     * @return string The base template class name
     */
    public function getBaseTemplateClass()
    {
        return $this->baseTemplateClass;
    }
    /**
     * Sets the base template class for compiled templates.
     *
     * @param string $class The base template class name
     */
    public function setBaseTemplateClass($class)
    {
        $this->baseTemplateClass = $class;
    }
    /**
     * Enables debugging mode.
     */
    public function enableDebug()
    {
        $this->debug = true;
    }
    /**
     * Disables debugging mode.
     */
    public function disableDebug()
    {
        $this->debug = false;
    }
    /**
     * Checks if debug mode is enabled.
     *
     * @return Boolean true if debug mode is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->debug;
    }
    /**
     * Enables the auto_reload option.
     */
    public function enableAutoReload()
    {
        $this->autoReload = true;
    }
    /**
     * Disables the auto_reload option.
     */
    public function disableAutoReload()
    {
        $this->autoReload = false;
    }
    /**
     * Checks if the auto_reload option is enabled.
     *
     * @return Boolean true if auto_reload is enabled, false otherwise
     */
    public function isAutoReload()
    {
        return $this->autoReload;
    }
    /**
     * Enables the strict_variables option.
     */
    public function enableStrictVariables()
    {
        $this->strictVariables = true;
    }
    /**
     * Disables the strict_variables option.
     */
    public function disableStrictVariables()
    {
        $this->strictVariables = false;
    }
    /**
     * Checks if the strict_variables option is enabled.
     *
     * @return Boolean true if strict_variables is enabled, false otherwise
     */
    public function isStrictVariables()
    {
        return $this->strictVariables;
    }
    /**
     * Gets the cache directory or false if cache is disabled.
     *
     * @return string|false
     */
    public function getCache()
    {
        return $this->cache;
    }
     /**
      * Sets the cache directory or false if cache is disabled.
      *
      * @param string|false $cache The absolute path to the compiled templates,
      *                            or false to disable cache
      */
    public function setCache($cache)
    {
        $this->cache = $cache ? $cache : false;
    }
    /**
     * Gets the cache filename for a given template.
     *
     * @param string $name The template name
     *
     * @return string|false The cache file name or false when caching is disabled
     */
    public function getCacheFilename($name)
    {
        if (false === $this->cache) {
            return false;
        }
        $class = substr($this->getTemplateClass($name), strlen($this->templateClassPrefix));
        return $this->getCache().'/'.substr($class, 0, 2).'/'.substr($class, 2, 2).'/'.substr($class, 4).'.php';
    }
    /**
     * Gets the template class associated with the given string.
     *
     * @param string  $name  The name for which to calculate the template class name
     * @param integer $index The index if it is an embedded template
     *
     * @return string The template class name
     */
    public function getTemplateClass($name, $index = null)
    {
        return $this->templateClassPrefix.hash('sha256', $this->getLoader()->getCacheKey($name)).(null === $index ? '' : '_'.$index);
    }
    /**
     * Gets the template class prefix.
     *
     * @return string The template class prefix
     */
    public function getTemplateClassPrefix()
    {
        return $this->templateClassPrefix;
    }
    /**
     * Renders a template.
     *
     * @param string $name    The template name
     * @param array  $context An array of parameters to pass to the template
     *
     * @return string The rendered template
     *
     * @throws Twig_Error_Loader  When the template cannot be found
     * @throws Twig_Error_Syntax  When an error occurred during compilation
     * @throws Twig_Error_Runtime When an error occurred during rendering
     */
    public function render($name, array $context = array())
    {
        return $this->loadTemplate($name)->render($context);
    }
    /**
     * Displays a template.
     *
     * @param string $name    The template name
     * @param array  $context An array of parameters to pass to the template
     *
     * @throws Twig_Error_Loader  When the template cannot be found
     * @throws Twig_Error_Syntax  When an error occurred during compilation
     * @throws Twig_Error_Runtime When an error occurred during rendering
     */
    public function display($name, array $context = array())
    {
        $this->loadTemplate($name)->display($context);
    }
    /**
     * Loads a template by name.
     *
     * @param string  $name  The template name
     * @param integer $index The index if it is an embedded template
     *
     * @return Twig_TemplateInterface A template instance representing the given template name
     *
     * @throws Twig_Error_Loader When the template cannot be found
     * @throws Twig_Error_Syntax When an error occurred during compilation
     */
    public function loadTemplate($name, $index = null)
    {
        $cls = $this->getTemplateClass($name, $index);
        if (isset($this->loadedTemplates[$cls])) {
            return $this->loadedTemplates[$cls];
        }
        if (!class_exists($cls, false)) {
            if (false === $cache = $this->getCacheFilename($name)) {
                eval('?>'.$this->compileSource($this->getLoader()->getSource($name), $name));
            } else {
                if (!is_file($cache) || ($this->isAutoReload() && !$this->isTemplateFresh($name, filemtime($cache)))) {
                    $this->writeCacheFile($cache, $this->compileSource($this->getLoader()->getSource($name), $name));
                }
                require_once $cache;
            }
        }
        if (!$this->runtimeInitialized) {
            $this->initRuntime();
        }
        return $this->loadedTemplates[$cls] = new $cls($this);
    }
    /**
     * Returns true if the template is still fresh.
     *
     * Besides checking the loader for freshness information,
     * this method also checks if the enabled extensions have
     * not changed.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     *
     * @return Boolean true if the template is fresh, false otherwise
     */
    public function isTemplateFresh($name, $time)
    {
        foreach ($this->extensions as $extension) {
            $r = new ReflectionObject($extension);
            if (filemtime($r->getFileName()) > $time) {
                return false;
            }
        }
        return $this->getLoader()->isFresh($name, $time);
    }
    /**
     * Tries to load a template consecutively from an array.
     *
     * Similar to loadTemplate() but it also accepts Twig_TemplateInterface instances and an array
     * of templates where each is tried to be loaded.
     *
     * @param string|Twig_Template|array $names A template or an array of templates to try consecutively
     *
     * @return Twig_Template
     *
     * @throws Twig_Error_Loader When none of the templates can be found
     * @throws Twig_Error_Syntax When an error occurred during compilation
     */
    public function resolveTemplate($names)
    {
        if (!is_array($names)) {
            $names = array($names);
        }
        foreach ($names as $name) {
            if ($name instanceof Twig_Template) {
                return $name;
            }
            try {
                return $this->loadTemplate($name);
            } catch (Twig_Error_Loader $e) {
            }
        }
        if (1 === count($names)) {
            throw $e;
        }
        throw new Twig_Error_Loader(sprintf('Unable to find one of the following templates: "%s".', implode('", "', $names)));
    }
    /**
     * Clears the internal template cache.
     */
    public function clearTemplateCache()
    {
        $this->loadedTemplates = array();
    }
    /**
     * Clears the template cache files on the filesystem.
     */
    public function clearCacheFiles()
    {
        if (false === $this->cache) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->cache), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if ($file->isFile()) {
                @unlink($file->getPathname());
            }
        }
    }
    /**
     * Gets the Lexer instance.
     *
     * @return Twig_LexerInterface A Twig_LexerInterface instance
     */
    public function getLexer()
    {
        if (null === $this->lexer) {
            $this->lexer = new Twig_Lexer($this);
        }
        return $this->lexer;
    }
    /**
     * Sets the Lexer instance.
     *
     * @param Twig_LexerInterface A Twig_LexerInterface instance
     */
    public function setLexer(Twig_LexerInterface $lexer)
    {
        $this->lexer = $lexer;
    }
    /**
     * Tokenizes a source code.
     *
     * @param string $source The template source code
     * @param string $name   The template name
     *
     * @return Twig_TokenStream A Twig_TokenStream instance
     *
     * @throws Twig_Error_Syntax When the code is syntactically wrong
     */
    public function tokenize($source, $name = null)
    {
        return $this->getLexer()->tokenize($source, $name);
    }
    /**
     * Gets the Parser instance.
     *
     * @return Twig_ParserInterface A Twig_ParserInterface instance
     */
    public function getParser()
    {
        if (null === $this->parser) {
            $this->parser = new Twig_Parser($this);
        }
        return $this->parser;
    }
    /**
     * Sets the Parser instance.
     *
     * @param Twig_ParserInterface A Twig_ParserInterface instance
     */
    public function setParser(Twig_ParserInterface $parser)
    {
        $this->parser = $parser;
    }
    /**
     * Converts a token stream to a node tree.
     *
     * @param Twig_TokenStream $stream A token stream instance
     *
     * @return Twig_Node_Module A node tree
     *
     * @throws Twig_Error_Syntax When the token stream is syntactically or semantically wrong
     */
    public function parse(Twig_TokenStream $stream)
    {
        return $this->getParser()->parse($stream);
    }
    /**
     * Gets the Compiler instance.
     *
     * @return Twig_CompilerInterface A Twig_CompilerInterface instance
     */
    public function getCompiler()
    {
        if (null === $this->compiler) {
            $this->compiler = new Twig_Compiler($this);
        }
        return $this->compiler;
    }
    /**
     * Sets the Compiler instance.
     *
     * @param Twig_CompilerInterface $compiler A Twig_CompilerInterface instance
     */
    public function setCompiler(Twig_CompilerInterface $compiler)
    {
        $this->compiler = $compiler;
    }
    /**
     * Compiles a node and returns the PHP code.
     *
     * @param Twig_NodeInterface $node A Twig_NodeInterface instance
     *
     * @return string The compiled PHP source code
     */
    public function compile(Twig_NodeInterface $node)
    {
        return $this->getCompiler()->compile($node)->getSource();
    }
    /**
     * Compiles a template source code.
     *
     * @param string $source The template source code
     * @param string $name   The template name
     *
     * @return string The compiled PHP source code
     *
     * @throws Twig_Error_Syntax When there was an error during tokenizing, parsing or compiling
     */
    public function compileSource($source, $name = null)
    {
        try {
            return $this->compile($this->parse($this->tokenize($source, $name)));
        } catch (Twig_Error $e) {
            $e->setTemplateFile($name);
            throw $e;
        } catch (Exception $e) {
            throw new Twig_Error_Syntax(sprintf('An exception has been thrown during the compilation of a template ("%s").', $e->getMessage()), -1, $name, $e);
        }
    }
    /**
     * Sets the Loader instance.
     *
     * @param Twig_LoaderInterface $loader A Twig_LoaderInterface instance
     */
    public function setLoader(Twig_LoaderInterface $loader)
    {
        $this->loader = $loader;
    }
    /**
     * Gets the Loader instance.
     *
     * @return Twig_LoaderInterface A Twig_LoaderInterface instance
     */
    public function getLoader()
    {
        if (null === $this->loader) {
            throw new LogicException('You must set a loader first.');
        }
        return $this->loader;
    }
    /**
     * Sets the default template charset.
     *
     * @param string $charset The default charset
     */
    public function setCharset($charset)
    {
        $this->charset = strtoupper($charset);
    }
    /**
     * Gets the default template charset.
     *
     * @return string The default charset
     */
    public function getCharset()
    {
        return $this->charset;
    }
    /**
     * Initializes the runtime environment.
     */
    public function initRuntime()
    {
        $this->runtimeInitialized = true;
        foreach ($this->getExtensions() as $extension) {
            $extension->initRuntime($this);
        }
    }
    /**
     * Returns true if the given extension is registered.
     *
     * @param string $name The extension name
     *
     * @return Boolean Whether the extension is registered or not
     */
    public function hasExtension($name)
    {
        return isset($this->extensions[$name]);
    }
    /**
     * Gets an extension by name.
     *
     * @param string $name The extension name
     *
     * @return Twig_ExtensionInterface A Twig_ExtensionInterface instance
     */
    public function getExtension($name)
    {
        if (!isset($this->extensions[$name])) {
            throw new Twig_Error_Runtime(sprintf('The "%s" extension is not enabled.', $name));
        }
        return $this->extensions[$name];
    }
    /**
     * Registers an extension.
     *
     * @param Twig_ExtensionInterface $extension A Twig_ExtensionInterface instance
     */
    public function addExtension(Twig_ExtensionInterface $extension)
    {
        if ($this->extensionInitialized) {
            throw new LogicException(sprintf('Unable to register extension "%s" as extensions have already been initialized.', $extension->getName()));
        }
        $this->extensions[$extension->getName()] = $extension;
    }
    /**
     * Removes an extension by name.
     *
     * This method is deprecated and you should not use it.
     *
     * @param string $name The extension name
     *
     * @deprecated since 1.12 (to be removed in 2.0)
     */
    public function removeExtension($name)
    {
        if ($this->extensionInitialized) {
            throw new LogicException(sprintf('Unable to remove extension "%s" as extensions have already been initialized.', $name));
        }
        unset($this->extensions[$name]);
    }
    /**
     * Registers an array of extensions.
     *
     * @param array $extensions An array of extensions
     */
    public function setExtensions(array $extensions)
    {
        foreach ($extensions as $extension) {
            $this->addExtension($extension);
        }
    }
    /**
     * Returns all registered extensions.
     *
     * @return array An array of extensions
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
    /**
     * Registers a Token Parser.
     *
     * @param Twig_TokenParserInterface $parser A Twig_TokenParserInterface instance
     */
    public function addTokenParser(Twig_TokenParserInterface $parser)
    {
        if ($this->extensionInitialized) {
            throw new LogicException('Unable to add a token parser as extensions have already been initialized.');
        }
        $this->staging->addTokenParser($parser);
    }
    /**
     * Gets the registered Token Parsers.
     *
     * @return Twig_TokenParserBrokerInterface A broker containing token parsers
     */
    public function getTokenParsers()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->parsers;
    }
    /**
     * Gets registered tags.
     *
     * Be warned that this method cannot return tags defined by Twig_TokenParserBrokerInterface classes.
     *
     * @return Twig_TokenParserInterface[] An array of Twig_TokenParserInterface instances
     */
    public function getTags()
    {
        $tags = array();
        foreach ($this->getTokenParsers()->getParsers() as $parser) {
            if ($parser instanceof Twig_TokenParserInterface) {
                $tags[$parser->getTag()] = $parser;
            }
        }
        return $tags;
    }
    /**
     * Registers a Node Visitor.
     *
     * @param Twig_NodeVisitorInterface $visitor A Twig_NodeVisitorInterface instance
     */
    public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
    {
        if ($this->extensionInitialized) {
            throw new LogicException('Unable to add a node visitor as extensions have already been initialized.');
        }
        $this->staging->addNodeVisitor($visitor);
    }
    /**
     * Gets the registered Node Visitors.
     *
     * @return Twig_NodeVisitorInterface[] An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->visitors;
    }
    /**
     * Registers a Filter.
     *
     * @param string|Twig_SimpleFilter               $name   The filter name or a Twig_SimpleFilter instance
     * @param Twig_FilterInterface|Twig_SimpleFilter $filter A Twig_FilterInterface instance or a Twig_SimpleFilter instance
     */
    public function addFilter($name, $filter = null)
    {
        if (!$name instanceof Twig_SimpleFilter && !($filter instanceof Twig_SimpleFilter || $filter instanceof Twig_FilterInterface)) {
            throw new LogicException('A filter must be an instance of Twig_FilterInterface or Twig_SimpleFilter');
        }
        if ($name instanceof Twig_SimpleFilter) {
            $filter = $name;
            $name = $filter->getName();
        }
        if ($this->extensionInitialized) {
            throw new LogicException(sprintf('Unable to add filter "%s" as extensions have already been initialized.', $name));
        }
        $this->staging->addFilter($name, $filter);
    }
    /**
     * Get a filter by name.
     *
     * Subclasses may override this method and load filters differently;
     * so no list of filters is available.
     *
     * @param string $name The filter name
     *
     * @return Twig_Filter|false A Twig_Filter instance or false if the filter does not exist
     */
    public function getFilter($name)
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        if (isset($this->filters[$name])) {
            return $this->filters[$name];
        }
        foreach ($this->filters as $pattern => $filter) {
            $pattern = str_replace('\\*', '(.*?)', preg_quote($pattern, '#'), $count);
            if ($count) {
                if (preg_match('#^'.$pattern.'$#', $name, $matches)) {
                    array_shift($matches);
                    $filter->setArguments($matches);
                    return $filter;
                }
            }
        }
        foreach ($this->filterCallbacks as $callback) {
            if (false !== $filter = call_user_func($callback, $name)) {
                return $filter;
            }
        }
        return false;
    }
    public function registerUndefinedFilterCallback($callable)
    {
        $this->filterCallbacks[] = $callable;
    }
    /**
     * Gets the registered Filters.
     *
     * Be warned that this method cannot return filters defined with registerUndefinedFunctionCallback.
     *
     * @return Twig_FilterInterface[] An array of Twig_FilterInterface instances
     *
     * @see registerUndefinedFilterCallback
     */
    public function getFilters()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->filters;
    }
    /**
     * Registers a Test.
     *
     * @param string|Twig_SimpleTest             $name The test name or a Twig_SimpleTest instance
     * @param Twig_TestInterface|Twig_SimpleTest $test A Twig_TestInterface instance or a Twig_SimpleTest instance
     */
    public function addTest($name, $test = null)
    {
        if (!$name instanceof Twig_SimpleTest && !($test instanceof Twig_SimpleTest || $test instanceof Twig_TestInterface)) {
            throw new LogicException('A test must be an instance of Twig_TestInterface or Twig_SimpleTest');
        }
        if ($name instanceof Twig_SimpleTest) {
            $test = $name;
            $name = $test->getName();
        }
        if ($this->extensionInitialized) {
            throw new LogicException(sprintf('Unable to add test "%s" as extensions have already been initialized.', $name));
        }
        $this->staging->addTest($name, $test);
    }
    /**
     * Gets the registered Tests.
     *
     * @return Twig_TestInterface[] An array of Twig_TestInterface instances
     */
    public function getTests()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->tests;
    }
    /**
     * Gets a test by name.
     *
     * @param string $name The test name
     *
     * @return Twig_Test|false A Twig_Test instance or false if the test does not exist
     */
    public function getTest($name)
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        if (isset($this->tests[$name])) {
            return $this->tests[$name];
        }
        return false;
    }
    /**
     * Registers a Function.
     *
     * @param string|Twig_SimpleFunction                 $name     The function name or a Twig_SimpleFunction instance
     * @param Twig_FunctionInterface|Twig_SimpleFunction $function A Twig_FunctionInterface instance or a Twig_SimpleFunction instance
     */
    public function addFunction($name, $function = null)
    {
        if (!$name instanceof Twig_SimpleFunction && !($function instanceof Twig_SimpleFunction || $function instanceof Twig_FunctionInterface)) {
            throw new LogicException('A function must be an instance of Twig_FunctionInterface or Twig_SimpleFunction');
        }
        if ($name instanceof Twig_SimpleFunction) {
            $function = $name;
            $name = $function->getName();
        }
        if ($this->extensionInitialized) {
            throw new LogicException(sprintf('Unable to add function "%s" as extensions have already been initialized.', $name));
        }
        $this->staging->addFunction($name, $function);
    }
    /**
     * Get a function by name.
     *
     * Subclasses may override this method and load functions differently;
     * so no list of functions is available.
     *
     * @param string $name function name
     *
     * @return Twig_Function|false A Twig_Function instance or false if the function does not exist
     */
    public function getFunction($name)
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }
        foreach ($this->functions as $pattern => $function) {
            $pattern = str_replace('\\*', '(.*?)', preg_quote($pattern, '#'), $count);
            if ($count) {
                if (preg_match('#^'.$pattern.'$#', $name, $matches)) {
                    array_shift($matches);
                    $function->setArguments($matches);
                    return $function;
                }
            }
        }
        foreach ($this->functionCallbacks as $callback) {
            if (false !== $function = call_user_func($callback, $name)) {
                return $function;
            }
        }
        return false;
    }
    public function registerUndefinedFunctionCallback($callable)
    {
        $this->functionCallbacks[] = $callable;
    }
    /**
     * Gets registered functions.
     *
     * Be warned that this method cannot return functions defined with registerUndefinedFunctionCallback.
     *
     * @return Twig_FunctionInterface[] An array of Twig_FunctionInterface instances
     *
     * @see registerUndefinedFunctionCallback
     */
    public function getFunctions()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->functions;
    }
    /**
     * Registers a Global.
     *
     * New globals can be added before compiling or rendering a template;
     * but after, you can only update existing globals.
     *
     * @param string $name  The global name
     * @param mixed  $value The global value
     */
    public function addGlobal($name, $value)
    {
        if ($this->extensionInitialized || $this->runtimeInitialized) {
            if (null === $this->globals) {
                $this->globals = $this->initGlobals();
            }
            /* This condition must be uncommented in Twig 2.0
            if (!array_key_exists($name, $this->globals)) {
                throw new LogicException(sprintf('Unable to add global "%s" as the runtime or the extensions have already been initialized.', $name));
            }
            */
        }
        if ($this->extensionInitialized || $this->runtimeInitialized) {
            // update the value
            $this->globals[$name] = $value;
        } else {
            $this->staging->addGlobal($name, $value);
        }
    }
    /**
     * Gets the registered Globals.
     *
     * @return array An array of globals
     */
    public function getGlobals()
    {
        if (!$this->runtimeInitialized && !$this->extensionInitialized) {
            return $this->initGlobals();
        }
        if (null === $this->globals) {
            $this->globals = $this->initGlobals();
        }
        return $this->globals;
    }
    /**
     * Merges a context with the defined globals.
     *
     * @param array $context An array representing the context
     *
     * @return array The context merged with the globals
     */
    public function mergeGlobals(array $context)
    {
        // we don't use array_merge as the context being generally
        // bigger than globals, this code is faster.
        foreach ($this->getGlobals() as $key => $value) {
            if (!array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }
        return $context;
    }
    /**
     * Gets the registered unary Operators.
     *
     * @return array An array of unary operators
     */
    public function getUnaryOperators()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->unaryOperators;
    }
    /**
     * Gets the registered binary Operators.
     *
     * @return array An array of binary operators
     */
    public function getBinaryOperators()
    {
        if (!$this->extensionInitialized) {
            $this->initExtensions();
        }
        return $this->binaryOperators;
    }
    public function computeAlternatives($name, $items)
    {
        $alternatives = array();
        foreach ($items as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = $lev;
            }
        }
        asort($alternatives);
        return array_keys($alternatives);
    }
    protected function initGlobals()
    {
        $globals = array();
        foreach ($this->extensions as $extension) {
            $extGlob = $extension->getGlobals();
            if (!is_array($extGlob)) {
                throw new UnexpectedValueException(sprintf('"%s::getGlobals()" must return an array of globals.', get_class($extension)));
            }
            $globals[] = $extGlob;
        }
        $globals[] = $this->staging->getGlobals();
        return call_user_func_array('array_merge', $globals);
    }
    protected function initExtensions()
    {
        if ($this->extensionInitialized) {
            return;
        }
        $this->extensionInitialized = true;
        $this->parsers = new Twig_TokenParserBroker();
        $this->filters = array();
        $this->functions = array();
        $this->tests = array();
        $this->visitors = array();
        $this->unaryOperators = array();
        $this->binaryOperators = array();
        foreach ($this->extensions as $extension) {
            $this->initExtension($extension);
        }
        $this->initExtension($this->staging);
    }
    protected function initExtension(Twig_ExtensionInterface $extension)
    {
        // filters
        foreach ($extension->getFilters() as $name => $filter) {
            if ($name instanceof Twig_SimpleFilter) {
                $filter = $name;
                $name = $filter->getName();
            } elseif ($filter instanceof Twig_SimpleFilter) {
                $name = $filter->getName();
            }
            $this->filters[$name] = $filter;
        }
        // functions
        foreach ($extension->getFunctions() as $name => $function) {
            if ($name instanceof Twig_SimpleFunction) {
                $function = $name;
                $name = $function->getName();
            } elseif ($function instanceof Twig_SimpleFunction) {
                $name = $function->getName();
            }
            $this->functions[$name] = $function;
        }
        // tests
        foreach ($extension->getTests() as $name => $test) {
            if ($name instanceof Twig_SimpleTest) {
                $test = $name;
                $name = $test->getName();
            } elseif ($test instanceof Twig_SimpleTest) {
                $name = $test->getName();
            }
            $this->tests[$name] = $test;
        }
        // token parsers
        foreach ($extension->getTokenParsers() as $parser) {
            if ($parser instanceof Twig_TokenParserInterface) {
                $this->parsers->addTokenParser($parser);
            } elseif ($parser instanceof Twig_TokenParserBrokerInterface) {
                $this->parsers->addTokenParserBroker($parser);
            } else {
                throw new LogicException('getTokenParsers() must return an array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances');
            }
        }
        // node visitors
        foreach ($extension->getNodeVisitors() as $visitor) {
            $this->visitors[] = $visitor;
        }
        // operators
        if ($operators = $extension->getOperators()) {
            if (2 !== count($operators)) {
                throw new InvalidArgumentException(sprintf('"%s::getOperators()" does not return a valid operators array.', get_class($extension)));
            }
            $this->unaryOperators = array_merge($this->unaryOperators, $operators[0]);
            $this->binaryOperators = array_merge($this->binaryOperators, $operators[1]);
        }
    }
    protected function writeCacheFile($file, $content)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf("Unable to create the cache directory (%s).", $dir));
            }
        } elseif (!is_writable($dir)) {
            throw new RuntimeException(sprintf("Unable to write in the cache directory (%s).", $dir));
        }
        $tmpFile = tempnam($dir, basename($file));
        if (false !== @file_put_contents($tmpFile, $content)) {
            // rename does not work on Win32 before 5.2.6
            if (@rename($tmpFile, $file) || (@copy($tmpFile, $file) && unlink($tmpFile))) {
                @chmod($file, 0666 & ~umask());
                return;
            }
        }
        throw new RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class Twig_Extension implements Twig_ExtensionInterface
{
    /**
     * Initializes the runtime environment.
     *
     * This is where you can load some file that contains filter functions for instance.
     *
     * @param Twig_Environment $environment The current Twig_Environment instance
     */
    public function initRuntime(Twig_Environment $environment)
    {
    }
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    public function getTokenParsers()
    {
        return array();
    }
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return Twig_NodeVisitorInterface[] An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        return array();
    }
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array();
    }
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array An array of tests
     */
    public function getTests()
    {
        return array();
    }
    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array();
    }
    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    public function getOperators()
    {
        return array();
    }
    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals()
    {
        return array();
    }
}

}

namespace
{

if (!defined('ENT_SUBSTITUTE')) {
    // use 0 as hhvm does not support several flags yet
    define('ENT_SUBSTITUTE', 0);
}
/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Core extends Twig_Extension
{
    protected $dateFormats = array('F j, Y H:i', '%d days');
    protected $numberFormat = array(0, '.', ',');
    protected $timezone = null;
    protected $escapers = array();
    /**
     * Defines a new escaper to be used via the escape filter.
     *
     * @param string   $strategy The strategy name that should be used as a strategy in the escape call
     * @param callable $callable A valid PHP callable
     */
    public function setEscaper($strategy, $callable)
    {
        $this->escapers[$strategy] = $callable;
    }
    /**
     * Gets all defined escapers.
     *
     * @return array An array of escapers
     */
    public function getEscapers()
    {
        return $this->escapers;
    }
    /**
     * Sets the default format to be used by the date filter.
     *
     * @param string $format             The default date format string
     * @param string $dateIntervalFormat The default date interval format string
     */
    public function setDateFormat($format = null, $dateIntervalFormat = null)
    {
        if (null !== $format) {
            $this->dateFormats[0] = $format;
        }
        if (null !== $dateIntervalFormat) {
            $this->dateFormats[1] = $dateIntervalFormat;
        }
    }
    /**
     * Gets the default format to be used by the date filter.
     *
     * @return array The default date format string and the default date interval format string
     */
    public function getDateFormat()
    {
        return $this->dateFormats;
    }
    /**
     * Sets the default timezone to be used by the date filter.
     *
     * @param DateTimeZone|string $timezone The default timezone string or a DateTimeZone object
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }
    /**
     * Gets the default timezone to be used by the date filter.
     *
     * @return DateTimeZone The default timezone currently in use
     */
    public function getTimezone()
    {
        if (null === $this->timezone) {
            $this->timezone = new DateTimeZone(date_default_timezone_get());
        }
        return $this->timezone;
    }
    /**
     * Sets the default format to be used by the number_format filter.
     *
     * @param integer $decimal      The number of decimal places to use.
     * @param string  $decimalPoint The character(s) to use for the decimal point.
     * @param string  $thousandSep  The character(s) to use for the thousands separator.
     */
    public function setNumberFormat($decimal, $decimalPoint, $thousandSep)
    {
        $this->numberFormat = array($decimal, $decimalPoint, $thousandSep);
    }
    /**
     * Get the default format used by the number_format filter.
     *
     * @return array The arguments for number_format()
     */
    public function getNumberFormat()
    {
        return $this->numberFormat;
    }
    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return Twig_TokenParser[] An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            new Twig_TokenParser_For(),
            new Twig_TokenParser_If(),
            new Twig_TokenParser_Extends(),
            new Twig_TokenParser_Include(),
            new Twig_TokenParser_Block(),
            new Twig_TokenParser_Use(),
            new Twig_TokenParser_Filter(),
            new Twig_TokenParser_Macro(),
            new Twig_TokenParser_Import(),
            new Twig_TokenParser_From(),
            new Twig_TokenParser_Set(),
            new Twig_TokenParser_Spaceless(),
            new Twig_TokenParser_Flush(),
            new Twig_TokenParser_Do(),
            new Twig_TokenParser_Embed(),
        );
    }
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        $filters = array(
            // formatting filters
            new Twig_SimpleFilter('date', 'twig_date_format_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('date_modify', 'twig_date_modify_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('format', 'sprintf'),
            new Twig_SimpleFilter('replace', 'strtr'),
            new Twig_SimpleFilter('number_format', 'twig_number_format_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('abs', 'abs'),
            new Twig_SimpleFilter('round', 'twig_round'),
            // encoding
            new Twig_SimpleFilter('url_encode', 'twig_urlencode_filter'),
            new Twig_SimpleFilter('json_encode', 'twig_jsonencode_filter'),
            new Twig_SimpleFilter('convert_encoding', 'twig_convert_encoding'),
            // string filters
            new Twig_SimpleFilter('title', 'twig_title_string_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('capitalize', 'twig_capitalize_string_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('upper', 'strtoupper'),
            new Twig_SimpleFilter('lower', 'strtolower'),
            new Twig_SimpleFilter('striptags', 'strip_tags'),
            new Twig_SimpleFilter('trim', 'trim'),
            new Twig_SimpleFilter('nl2br', 'nl2br', array('pre_escape' => 'html', 'is_safe' => array('html'))),
            // array helpers
            new Twig_SimpleFilter('join', 'twig_join_filter'),
            new Twig_SimpleFilter('split', 'twig_split_filter'),
            new Twig_SimpleFilter('sort', 'twig_sort_filter'),
            new Twig_SimpleFilter('merge', 'twig_array_merge'),
            new Twig_SimpleFilter('batch', 'twig_array_batch'),
            // string/array filters
            new Twig_SimpleFilter('reverse', 'twig_reverse_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('length', 'twig_length_filter', array('needs_environment' => true)),
            new Twig_SimpleFilter('slice', 'twig_slice', array('needs_environment' => true)),
            new Twig_SimpleFilter('first', 'twig_first', array('needs_environment' => true)),
            new Twig_SimpleFilter('last', 'twig_last', array('needs_environment' => true)),
            // iteration and runtime
            new Twig_SimpleFilter('default', '_twig_default_filter', array('node_class' => 'Twig_Node_Expression_Filter_Default')),
            new Twig_SimpleFilter('keys', 'twig_get_array_keys_filter'),
            // escaping
            new Twig_SimpleFilter('escape', 'twig_escape_filter', array('needs_environment' => true, 'is_safe_callback' => 'twig_escape_filter_is_safe')),
            new Twig_SimpleFilter('e', 'twig_escape_filter', array('needs_environment' => true, 'is_safe_callback' => 'twig_escape_filter_is_safe')),
        );
        if (function_exists('mb_get_info')) {
            $filters[] = new Twig_SimpleFilter('upper', 'twig_upper_filter', array('needs_environment' => true));
            $filters[] = new Twig_SimpleFilter('lower', 'twig_lower_filter', array('needs_environment' => true));
        }
        return $filters;
    }
    /**
     * Returns a list of global functions to add to the existing list.
     *
     * @return array An array of global functions
     */
    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('max', 'max'),
            new Twig_SimpleFunction('min', 'min'),
            new Twig_SimpleFunction('range', 'range'),
            new Twig_SimpleFunction('constant', 'twig_constant'),
            new Twig_SimpleFunction('cycle', 'twig_cycle'),
            new Twig_SimpleFunction('random', 'twig_random', array('needs_environment' => true)),
            new Twig_SimpleFunction('date', 'twig_date_converter', array('needs_environment' => true)),
            new Twig_SimpleFunction('include', 'twig_include', array('needs_environment' => true, 'needs_context' => true, 'is_safe' => array('all'))),
            new Twig_SimpleFunction('source', 'twig_source', array('needs_environment' => true, 'is_safe' => array('all'))),
        );
    }
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array An array of tests
     */
    public function getTests()
    {
        return array(
            new Twig_SimpleTest('even', null, array('node_class' => 'Twig_Node_Expression_Test_Even')),
            new Twig_SimpleTest('odd', null, array('node_class' => 'Twig_Node_Expression_Test_Odd')),
            new Twig_SimpleTest('defined', null, array('node_class' => 'Twig_Node_Expression_Test_Defined')),
            new Twig_SimpleTest('sameas', null, array('node_class' => 'Twig_Node_Expression_Test_Sameas')),
            new Twig_SimpleTest('same as', null, array('node_class' => 'Twig_Node_Expression_Test_Sameas')),
            new Twig_SimpleTest('none', null, array('node_class' => 'Twig_Node_Expression_Test_Null')),
            new Twig_SimpleTest('null', null, array('node_class' => 'Twig_Node_Expression_Test_Null')),
            new Twig_SimpleTest('divisibleby', null, array('node_class' => 'Twig_Node_Expression_Test_Divisibleby')),
            new Twig_SimpleTest('divisible by', null, array('node_class' => 'Twig_Node_Expression_Test_Divisibleby')),
            new Twig_SimpleTest('constant', null, array('node_class' => 'Twig_Node_Expression_Test_Constant')),
            new Twig_SimpleTest('empty', 'twig_test_empty'),
            new Twig_SimpleTest('iterable', 'twig_test_iterable'),
        );
    }
    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    public function getOperators()
    {
        return array(
            array(
                'not' => array('precedence' => 50, 'class' => 'Twig_Node_Expression_Unary_Not'),
                '-'   => array('precedence' => 500, 'class' => 'Twig_Node_Expression_Unary_Neg'),
                '+'   => array('precedence' => 500, 'class' => 'Twig_Node_Expression_Unary_Pos'),
            ),
            array(
                'or'          => array('precedence' => 10, 'class' => 'Twig_Node_Expression_Binary_Or', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'and'         => array('precedence' => 15, 'class' => 'Twig_Node_Expression_Binary_And', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'b-or'        => array('precedence' => 16, 'class' => 'Twig_Node_Expression_Binary_BitwiseOr', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'b-xor'       => array('precedence' => 17, 'class' => 'Twig_Node_Expression_Binary_BitwiseXor', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'b-and'       => array('precedence' => 18, 'class' => 'Twig_Node_Expression_Binary_BitwiseAnd', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '=='          => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Equal', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '!='          => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_NotEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '<'           => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Less', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '>'           => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Greater', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '>='          => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_GreaterEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '<='          => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_LessEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'not in'      => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_NotIn', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'in'          => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_In', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'matches'     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Matches', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'starts with' => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_StartsWith', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'ends with'   => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_EndsWith', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '..'          => array('precedence' => 25, 'class' => 'Twig_Node_Expression_Binary_Range', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '+'           => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Add', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '-'           => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Sub', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '~'           => array('precedence' => 40, 'class' => 'Twig_Node_Expression_Binary_Concat', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '*'           => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Mul', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '/'           => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Div', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '//'          => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_FloorDiv', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '%'           => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Mod', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'is'          => array('precedence' => 100, 'callable' => array($this, 'parseTestExpression'), 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'is not'      => array('precedence' => 100, 'callable' => array($this, 'parseNotTestExpression'), 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '**'          => array('precedence' => 200, 'class' => 'Twig_Node_Expression_Binary_Power', 'associativity' => Twig_ExpressionParser::OPERATOR_RIGHT),
            ),
        );
    }
    public function parseNotTestExpression(Twig_Parser $parser, Twig_NodeInterface $node)
    {
        return new Twig_Node_Expression_Unary_Not($this->parseTestExpression($parser, $node), $parser->getCurrentToken()->getLine());
    }
    public function parseTestExpression(Twig_Parser $parser, Twig_NodeInterface $node)
    {
        $stream = $parser->getStream();
        $name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
        $class = $this->getTestNodeClass($parser, $name, $node->getLine());
        $arguments = null;
        if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
            $arguments = $parser->getExpressionParser()->parseArguments(true);
        }
        return new $class($node, $name, $arguments, $parser->getCurrentToken()->getLine());
    }
    protected function getTestNodeClass(Twig_Parser $parser, $name, $line)
    {
        $env = $parser->getEnvironment();
        $testMap = $env->getTests();
        $testName = null;
        if (isset($testMap[$name])) {
            $testName = $name;
        } elseif ($parser->getStream()->test(Twig_Token::NAME_TYPE)) {
            // try 2-words tests
            $name = $name.' '.$parser->getCurrentToken()->getValue();
            if (isset($testMap[$name])) {
                $parser->getStream()->next();
                $testName = $name;
            }
        }
        if (null === $testName) {
            $message = sprintf('The test "%s" does not exist', $name);
            if ($alternatives = $env->computeAlternatives($name, array_keys($env->getTests()))) {
                $message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
            }
            throw new Twig_Error_Syntax($message, $line, $parser->getFilename());
        }
        if ($testMap[$name] instanceof Twig_SimpleTest) {
            return $testMap[$name]->getNodeClass();
        }
        return $testMap[$name] instanceof Twig_Test_Node ? $testMap[$name]->getClass() : 'Twig_Node_Expression_Test';
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'core';
    }
}
/**
 * Cycles over a value.
 *
 * @param ArrayAccess|array $values   An array or an ArrayAccess instance
 * @param integer           $position The cycle position
 *
 * @return string The next value in the cycle
 */
function twig_cycle($values, $position)
{
    if (!is_array($values) && !$values instanceof ArrayAccess) {
        return $values;
    }
    return $values[$position % count($values)];
}
/**
 * Returns a random value depending on the supplied parameter type:
 * - a random item from a Traversable or array
 * - a random character from a string
 * - a random integer between 0 and the integer parameter
 *
 * @param Twig_Environment                 $env    A Twig_Environment instance
 * @param Traversable|array|integer|string $values The values to pick a random item from
 *
 * @throws Twig_Error_Runtime When $values is an empty array (does not apply to an empty string which is returned as is).
 *
 * @return mixed A random value from the given sequence
 */
function twig_random(Twig_Environment $env, $values = null)
{
    if (null === $values) {
        return mt_rand();
    }
    if (is_int($values) || is_float($values)) {
        return $values < 0 ? mt_rand($values, 0) : mt_rand(0, $values);
    }
    if (is_object($values) && $values instanceof Traversable) {
        $values = iterator_to_array($values);
    } elseif (is_string($values)) {
        if ('' === $values) {
            return '';
        }
        if (null !== $charset = $env->getCharset()) {
            if ('UTF-8' != $charset) {
                $values = twig_convert_encoding($values, 'UTF-8', $charset);
            }
            // unicode version of str_split()
            // split at all positions, but not after the start and not before the end
            $values = preg_split('/(?<!^)(?!$)/u', $values);
            if ('UTF-8' != $charset) {
                foreach ($values as $i => $value) {
                    $values[$i] = twig_convert_encoding($value, $charset, 'UTF-8');
                }
            }
        } else {
            return $values[mt_rand(0, strlen($values) - 1)];
        }
    }
    if (!is_array($values)) {
        return $values;
    }
    if (0 === count($values)) {
        throw new Twig_Error_Runtime('The random function cannot pick from an empty array.');
    }
    return $values[array_rand($values, 1)];
}
/**
 * Converts a date to the given format.
 *
 * <pre>
 *   {{ post.published_at|date("m/d/Y") }}
 * </pre>
 *
 * @param Twig_Environment             $env      A Twig_Environment instance
 * @param DateTime|DateInterval|string $date     A date
 * @param string                       $format   A format
 * @param DateTimeZone|string          $timezone A timezone
 *
 * @return string The formatted date
 */
function twig_date_format_filter(Twig_Environment $env, $date, $format = null, $timezone = null)
{
    if (null === $format) {
        $formats = $env->getExtension('core')->getDateFormat();
        $format = $date instanceof DateInterval ? $formats[1] : $formats[0];
    }
    if ($date instanceof DateInterval) {
        return $date->format($format);
    }
    return twig_date_converter($env, $date, $timezone)->format($format);
}
/**
 * Returns a new date object modified
 *
 * <pre>
 *   {{ post.published_at|date_modify("-1day")|date("m/d/Y") }}
 * </pre>
 *
 * @param Twig_Environment  $env      A Twig_Environment instance
 * @param DateTime|string   $date     A date
 * @param string            $modifier A modifier string
 *
 * @return DateTime A new date object
 */
function twig_date_modify_filter(Twig_Environment $env, $date, $modifier)
{
    $date = twig_date_converter($env, $date, false);
    $date->modify($modifier);
    return $date;
}
/**
 * Converts an input to a DateTime instance.
 *
 * <pre>
 *    {% if date(user.created_at) < date('+2days') %}
 *      {# do something #}
 *    {% endif %}
 * </pre>
 *
 * @param Twig_Environment    $env      A Twig_Environment instance
 * @param DateTime|string     $date     A date
 * @param DateTimeZone|string $timezone A timezone
 *
 * @return DateTime A DateTime instance
 */
function twig_date_converter(Twig_Environment $env, $date = null, $timezone = null)
{
    // determine the timezone
    if (!$timezone) {
        $defaultTimezone = $env->getExtension('core')->getTimezone();
    } elseif (!$timezone instanceof DateTimeZone) {
        $defaultTimezone = new DateTimeZone($timezone);
    } else {
        $defaultTimezone = $timezone;
    }
    if ($date instanceof DateTime || $date instanceof DateTimeInterface) {
        $returningDate = new DateTime($date->format('c'));
        if (false !== $timezone) {
            $returningDate->setTimezone($defaultTimezone);
        } else {
            $returningDate->setTimezone($date->getTimezone());
        }
        return $returningDate;
    }
    $asString = (string) $date;
    if (ctype_digit($asString) || (!empty($asString) && '-' === $asString[0] && ctype_digit(substr($asString, 1)))) {
        $date = '@'.$date;
    }
    $date = new DateTime($date, $defaultTimezone);
    if (false !== $timezone) {
        $date->setTimezone($defaultTimezone);
    }
    return $date;
}
/**
 * Rounds a number.
 *
 * @param integer|float $value     The value to round
 * @param integer|float $precision The rounding precision
 * @param string        $method    The method to use for rounding
 *
 * @return integer|float The rounded number
 */
function twig_round($value, $precision = 0, $method = 'common')
{
    if ('common' == $method) {
        return round($value, $precision);
    }
    if ('ceil' != $method && 'floor' != $method) {
        throw new Twig_Error_Runtime('The round filter only supports the "common", "ceil", and "floor" methods.');
    }
    return $method($value * pow(10, $precision)) / pow(10, $precision);
}
/**
 * Number format filter.
 *
 * All of the formatting options can be left null, in that case the defaults will
 * be used.  Supplying any of the parameters will override the defaults set in the
 * environment object.
 *
 * @param Twig_Environment    $env          A Twig_Environment instance
 * @param mixed               $number       A float/int/string of the number to format
 * @param integer             $decimal      The number of decimal points to display.
 * @param string              $decimalPoint The character(s) to use for the decimal point.
 * @param string              $thousandSep  The character(s) to use for the thousands separator.
 *
 * @return string The formatted number
 */
function twig_number_format_filter(Twig_Environment $env, $number, $decimal = null, $decimalPoint = null, $thousandSep = null)
{
    $defaults = $env->getExtension('core')->getNumberFormat();
    if (null === $decimal) {
        $decimal = $defaults[0];
    }
    if (null === $decimalPoint) {
        $decimalPoint = $defaults[1];
    }
    if (null === $thousandSep) {
        $thousandSep = $defaults[2];
    }
    return number_format((float) $number, $decimal, $decimalPoint, $thousandSep);
}
/**
 * URL encodes a string as a path segment or an array as a query string.
 *
 * @param string|array $url A URL or an array of query parameters
 * @param Boolean      $raw true to use rawurlencode() instead of urlencode
 *
 * @return string The URL encoded value
 */
function twig_urlencode_filter($url, $raw = false)
{
    if (is_array($url)) {
        return http_build_query($url, '', '&');
    }
    if ($raw) {
        return rawurlencode($url);
    }
    return urlencode($url);
}
if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    /**
     * JSON encodes a variable.
     *
     * @param mixed   $value   The value to encode.
     * @param integer $options Not used on PHP 5.2.x
     *
     * @return mixed The JSON encoded value
     */
    function twig_jsonencode_filter($value, $options = 0)
    {
        if ($value instanceof Twig_Markup) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            array_walk_recursive($value, '_twig_markup2string');
        }
        return json_encode($value);
    }
} else {
    /**
     * JSON encodes a variable.
     *
     * @param mixed   $value   The value to encode.
     * @param integer $options Bitmask consisting of JSON_HEX_QUOT, JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, JSON_NUMERIC_CHECK, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT
     *
     * @return mixed The JSON encoded value
     */
    function twig_jsonencode_filter($value, $options = 0)
    {
        if ($value instanceof Twig_Markup) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            array_walk_recursive($value, '_twig_markup2string');
        }
        return json_encode($value, $options);
    }
}
function _twig_markup2string(&$value)
{
    if ($value instanceof Twig_Markup) {
        $value = (string) $value;
    }
}
/**
 * Merges an array with another one.
 *
 * <pre>
 *  {% set items = { 'apple': 'fruit', 'orange': 'fruit' } %}
 *
 *  {% set items = items|merge({ 'peugeot': 'car' }) %}
 *
 *  {# items now contains { 'apple': 'fruit', 'orange': 'fruit', 'peugeot': 'car' } #}
 * </pre>
 *
 * @param array $arr1 An array
 * @param array $arr2 An array
 *
 * @return array The merged array
 */
function twig_array_merge($arr1, $arr2)
{
    if (!is_array($arr1) || !is_array($arr2)) {
        throw new Twig_Error_Runtime('The merge filter only works with arrays or hashes.');
    }
    return array_merge($arr1, $arr2);
}
/**
 * Slices a variable.
 *
 * @param Twig_Environment $env          A Twig_Environment instance
 * @param mixed            $item         A variable
 * @param integer          $start        Start of the slice
 * @param integer          $length       Size of the slice
 * @param Boolean          $preserveKeys Whether to preserve key or not (when the input is an array)
 *
 * @return mixed The sliced variable
 */
function twig_slice(Twig_Environment $env, $item, $start, $length = null, $preserveKeys = false)
{
    if (is_object($item) && $item instanceof Traversable) {
        $item = iterator_to_array($item, false);
    }
    if (is_array($item)) {
        return array_slice($item, $start, $length, $preserveKeys);
    }
    $item = (string) $item;
    if (function_exists('mb_get_info') && null !== $charset = $env->getCharset()) {
        return mb_substr($item, $start, null === $length ? mb_strlen($item, $charset) - $start : $length, $charset);
    }
    return null === $length ? substr($item, $start) : substr($item, $start, $length);
}
/**
 * Returns the first element of the item.
 *
 * @param Twig_Environment $env  A Twig_Environment instance
 * @param mixed            $item A variable
 *
 * @return mixed The first element of the item
 */
function twig_first(Twig_Environment $env, $item)
{
    $elements = twig_slice($env, $item, 0, 1, false);
    return is_string($elements) ? $elements : current($elements);
}
/**
 * Returns the last element of the item.
 *
 * @param Twig_Environment $env  A Twig_Environment instance
 * @param mixed            $item A variable
 *
 * @return mixed The last element of the item
 */
function twig_last(Twig_Environment $env, $item)
{
    $elements = twig_slice($env, $item, -1, 1, false);
    return is_string($elements) ? $elements : current($elements);
}
/**
 * Joins the values to a string.
 *
 * The separator between elements is an empty string per default, you can define it with the optional parameter.
 *
 * <pre>
 *  {{ [1, 2, 3]|join('|') }}
 *  {# returns 1|2|3 #}
 *
 *  {{ [1, 2, 3]|join }}
 *  {# returns 123 #}
 * </pre>
 *
 * @param array  $value An array
 * @param string $glue  The separator
 *
 * @return string The concatenated string
 */
function twig_join_filter($value, $glue = '')
{
    if (is_object($value) && $value instanceof Traversable) {
        $value = iterator_to_array($value, false);
    }
    return implode($glue, (array) $value);
}
/**
 * Splits the string into an array.
 *
 * <pre>
 *  {{ "one,two,three"|split(',') }}
 *  {# returns [one, two, three] #}
 *
 *  {{ "one,two,three,four,five"|split(',', 3) }}
 *  {# returns [one, two, "three,four,five"] #}
 *
 *  {{ "123"|split('') }}
 *  {# returns [1, 2, 3] #}
 *
 *  {{ "aabbcc"|split('', 2) }}
 *  {# returns [aa, bb, cc] #}
 * </pre>
 *
 * @param string  $value     A string
 * @param string  $delimiter The delimiter
 * @param integer $limit     The limit
 *
 * @return array The split string as an array
 */
function twig_split_filter($value, $delimiter, $limit = null)
{
    if (empty($delimiter)) {
        return str_split($value, null === $limit ? 1 : $limit);
    }
    return null === $limit ? explode($delimiter, $value) : explode($delimiter, $value, $limit);
}
// The '_default' filter is used internally to avoid using the ternary operator
// which costs a lot for big contexts (before PHP 5.4). So, on average,
// a function call is cheaper.
function _twig_default_filter($value, $default = '')
{
    if (twig_test_empty($value)) {
        return $default;
    }
    return $value;
}
/**
 * Returns the keys for the given array.
 *
 * It is useful when you want to iterate over the keys of an array:
 *
 * <pre>
 *  {% for key in array|keys %}
 *      {# ... #}
 *  {% endfor %}
 * </pre>
 *
 * @param array $array An array
 *
 * @return array The keys
 */
function twig_get_array_keys_filter($array)
{
    if (is_object($array) && $array instanceof Traversable) {
        return array_keys(iterator_to_array($array));
    }
    if (!is_array($array)) {
        return array();
    }
    return array_keys($array);
}
/**
 * Reverses a variable.
 *
 * @param Twig_Environment         $env          A Twig_Environment instance
 * @param array|Traversable|string $item         An array, a Traversable instance, or a string
 * @param Boolean                  $preserveKeys Whether to preserve key or not
 *
 * @return mixed The reversed input
 */
function twig_reverse_filter(Twig_Environment $env, $item, $preserveKeys = false)
{
    if (is_object($item) && $item instanceof Traversable) {
        return array_reverse(iterator_to_array($item), $preserveKeys);
    }
    if (is_array($item)) {
        return array_reverse($item, $preserveKeys);
    }
    if (null !== $charset = $env->getCharset()) {
        $string = (string) $item;
        if ('UTF-8' != $charset) {
            $item = twig_convert_encoding($string, 'UTF-8', $charset);
        }
        preg_match_all('/./us', $item, $matches);
        $string = implode('', array_reverse($matches[0]));
        if ('UTF-8' != $charset) {
            $string = twig_convert_encoding($string, $charset, 'UTF-8');
        }
        return $string;
    }
    return strrev((string) $item);
}
/**
 * Sorts an array.
 *
 * @param array $array An array
 */
function twig_sort_filter($array)
{
    asort($array);
    return $array;
}
/* used internally */
function twig_in_filter($value, $compare)
{
    if (is_array($compare)) {
        return in_array($value, $compare, is_object($value));
    } elseif (is_string($compare)) {
        if (!strlen($value)) {
            return empty($compare);
        }
        return false !== strpos($compare, (string) $value);
    } elseif (is_object($compare) && $compare instanceof Traversable) {
        return in_array($value, iterator_to_array($compare, false), is_object($value));
    }
    return false;
}
/**
 * Escapes a string.
 *
 * @param Twig_Environment $env        A Twig_Environment instance
 * @param string           $string     The value to be escaped
 * @param string           $strategy   The escaping strategy
 * @param string           $charset    The charset
 * @param Boolean          $autoescape Whether the function is called by the auto-escaping feature (true) or by the developer (false)
 */
function twig_escape_filter(Twig_Environment $env, $string, $strategy = 'html', $charset = null, $autoescape = false)
{
    if ($autoescape && $string instanceof Twig_Markup) {
        return $string;
    }
    if (!is_string($string)) {
        if (is_object($string) && method_exists($string, '__toString')) {
            $string = (string) $string;
        } else {
            return $string;
        }
    }
    if (null === $charset) {
        $charset = $env->getCharset();
    }
    switch ($strategy) {
        case 'html':
            // see http://php.net/htmlspecialchars
            // Using a static variable to avoid initializing the array
            // each time the function is called. Moving the declaration on the
            // top of the function slow downs other escaping strategies.
            static $htmlspecialcharsCharsets;
            if (null === $htmlspecialcharsCharsets) {
                if ('hiphop' === substr(PHP_VERSION, -6)) {
                    $htmlspecialcharsCharsets = array('utf-8' => true, 'UTF-8' => true);
                } else {
                    $htmlspecialcharsCharsets = array(
                        'ISO-8859-1' => true, 'ISO8859-1' => true,
                        'ISO-8859-15' => true, 'ISO8859-15' => true,
                        'utf-8' => true, 'UTF-8' => true,
                        'CP866' => true, 'IBM866' => true, '866' => true,
                        'CP1251' => true, 'WINDOWS-1251' => true, 'WIN-1251' => true,
                        '1251' => true,
                        'CP1252' => true, 'WINDOWS-1252' => true, '1252' => true,
                        'KOI8-R' => true, 'KOI8-RU' => true, 'KOI8R' => true,
                        'BIG5' => true, '950' => true,
                        'GB2312' => true, '936' => true,
                        'BIG5-HKSCS' => true,
                        'SHIFT_JIS' => true, 'SJIS' => true, '932' => true,
                        'EUC-JP' => true, 'EUCJP' => true,
                        'ISO8859-5' => true, 'ISO-8859-5' => true, 'MACROMAN' => true,
                    );
                }
            }
            if (isset($htmlspecialcharsCharsets[$charset])) {
                return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            }
            if (isset($htmlspecialcharsCharsets[strtoupper($charset)])) {
                // cache the lowercase variant for future iterations
                $htmlspecialcharsCharsets[$charset] = true;
                return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            }
            $string = twig_convert_encoding($string, 'UTF-8', $charset);
            $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return twig_convert_encoding($string, $charset, 'UTF-8');
        case 'js':
            // escape all non-alphanumeric characters
            // into their \xHH or \uHHHH representations
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9,\._]#Su', '_twig_escape_js_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'css':
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9]#Su', '_twig_escape_css_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'html_attr':
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9,\.\-_]#Su', '_twig_escape_html_attr_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'url':
            // hackish test to avoid version_compare that is much slower, this works unless PHP releases a 5.10.*
            // at that point however PHP 5.2.* support can be removed
            if (PHP_VERSION < '5.3.0') {
                return str_replace('%7E', '~', rawurlencode($string));
            }
            return rawurlencode($string);
        default:
            static $escapers;
            if (null === $escapers) {
                $escapers = $env->getExtension('core')->getEscapers();
            }
            if (isset($escapers[$strategy])) {
                return call_user_func($escapers[$strategy], $env, $string, $charset);
            }
            $validStrategies = implode(', ', array_merge(array('html', 'js', 'url', 'css', 'html_attr'), array_keys($escapers)));
            throw new Twig_Error_Runtime(sprintf('Invalid escaping strategy "%s" (valid ones: %s).', $strategy, $validStrategies));
    }
}
/* used internally */
function twig_escape_filter_is_safe(Twig_Node $filterArgs)
{
    foreach ($filterArgs as $arg) {
        if ($arg instanceof Twig_Node_Expression_Constant) {
            return array($arg->getAttribute('value'));
        }
        return array();
    }
    return array('html');
}
if (function_exists('mb_convert_encoding')) {
    function twig_convert_encoding($string, $to, $from)
    {
        return mb_convert_encoding($string, $to, $from);
    }
} elseif (function_exists('iconv')) {
    function twig_convert_encoding($string, $to, $from)
    {
        return iconv($from, $to, $string);
    }
} else {
    function twig_convert_encoding($string, $to, $from)
    {
        throw new Twig_Error_Runtime('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
    }
}
function _twig_escape_js_callback($matches)
{
    $char = $matches[0];
    // \xHH
    if (!isset($char[1])) {
        return '\\x'.strtoupper(substr('00'.bin2hex($char), -2));
    }
    // \uHHHH
    $char = twig_convert_encoding($char, 'UTF-16BE', 'UTF-8');
    return '\\u'.strtoupper(substr('0000'.bin2hex($char), -4));
}
function _twig_escape_css_callback($matches)
{
    $char = $matches[0];
    // \xHH
    if (!isset($char[1])) {
        $hex = ltrim(strtoupper(bin2hex($char)), '0');
        if (0 === strlen($hex)) {
            $hex = '0';
        }
        return '\\'.$hex.' ';
    }
    // \uHHHH
    $char = twig_convert_encoding($char, 'UTF-16BE', 'UTF-8');
    return '\\'.ltrim(strtoupper(bin2hex($char)), '0').' ';
}
/**
 * This function is adapted from code coming from Zend Framework.
 *
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
function _twig_escape_html_attr_callback($matches)
{
    /*
     * While HTML supports far more named entities, the lowest common denominator
     * has become HTML5's XML Serialisation which is restricted to the those named
     * entities that XML supports. Using HTML entities would result in this error:
     *     XML Parsing Error: undefined entity
     */
    static $entityMap = array(
        34 => 'quot', /* quotation mark */
        38 => 'amp',  /* ampersand */
        60 => 'lt',   /* less-than sign */
        62 => 'gt',   /* greater-than sign */
    );
    $chr = $matches[0];
    $ord = ord($chr);
    /**
     * The following replaces characters undefined in HTML with the
     * hex entity for the Unicode replacement character.
     */
    if (($ord <= 0x1f && $chr != "\t" && $chr != "\n" && $chr != "\r") || ($ord >= 0x7f && $ord <= 0x9f)) {
        return '&#xFFFD;';
    }
    /**
     * Check if the current character to escape has a name entity we should
     * replace it with while grabbing the hex value of the character.
     */
    if (strlen($chr) == 1) {
        $hex = strtoupper(substr('00'.bin2hex($chr), -2));
    } else {
        $chr = twig_convert_encoding($chr, 'UTF-16BE', 'UTF-8');
        $hex = strtoupper(substr('0000'.bin2hex($chr), -4));
    }
    $int = hexdec($hex);
    if (array_key_exists($int, $entityMap)) {
        return sprintf('&%s;', $entityMap[$int]);
    }
    /**
     * Per OWASP recommendations, we'll use hex entities for any other
     * characters where a named entity does not exist.
     */
    return sprintf('&#x%s;', $hex);
}
// add multibyte extensions if possible
if (function_exists('mb_get_info')) {
    /**
     * Returns the length of a variable.
     *
     * @param Twig_Environment $env   A Twig_Environment instance
     * @param mixed            $thing A variable
     *
     * @return integer The length of the value
     */
    function twig_length_filter(Twig_Environment $env, $thing)
    {
        return is_scalar($thing) ? mb_strlen($thing, $env->getCharset()) : count($thing);
    }
    /**
     * Converts a string to uppercase.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The uppercased string
     */
    function twig_upper_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtoupper($string, $charset);
        }
        return strtoupper($string);
    }
    /**
     * Converts a string to lowercase.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The lowercased string
     */
    function twig_lower_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtolower($string, $charset);
        }
        return strtolower($string);
    }
    /**
     * Returns a titlecased string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The titlecased string
     */
    function twig_title_string_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_convert_case($string, MB_CASE_TITLE, $charset);
        }
        return ucwords(strtolower($string));
    }
    /**
     * Returns a capitalized string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The capitalized string
     */
    function twig_capitalize_string_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtoupper(mb_substr($string, 0, 1, $charset), $charset).
                         mb_strtolower(mb_substr($string, 1, mb_strlen($string, $charset), $charset), $charset);
        }
        return ucfirst(strtolower($string));
    }
}
// and byte fallback
else {
    /**
     * Returns the length of a variable.
     *
     * @param Twig_Environment $env   A Twig_Environment instance
     * @param mixed            $thing A variable
     *
     * @return integer The length of the value
     */
    function twig_length_filter(Twig_Environment $env, $thing)
    {
        return is_scalar($thing) ? strlen($thing) : count($thing);
    }
    /**
     * Returns a titlecased string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The titlecased string
     */
    function twig_title_string_filter(Twig_Environment $env, $string)
    {
        return ucwords(strtolower($string));
    }
    /**
     * Returns a capitalized string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The capitalized string
     */
    function twig_capitalize_string_filter(Twig_Environment $env, $string)
    {
        return ucfirst(strtolower($string));
    }
}
/* used internally */
function twig_ensure_traversable($seq)
{
    if ($seq instanceof Traversable || is_array($seq)) {
        return $seq;
    }
    return array();
}
/**
 * Checks if a variable is empty.
 *
 * <pre>
 * {# evaluates to true if the foo variable is null, false, or the empty string #}
 * {% if foo is empty %}
 *     {# ... #}
 * {% endif %}
 * </pre>
 *
 * @param mixed $value A variable
 *
 * @return Boolean true if the value is empty, false otherwise
 */
function twig_test_empty($value)
{
    if ($value instanceof Countable) {
        return 0 == count($value);
    }
    return '' === $value || false === $value || null === $value || array() === $value;
}
/**
 * Checks if a variable is traversable.
 *
 * <pre>
 * {# evaluates to true if the foo variable is an array or a traversable object #}
 * {% if foo is traversable %}
 *     {# ... #}
 * {% endif %}
 * </pre>
 *
 * @param mixed $value A variable
 *
 * @return Boolean true if the value is traversable
 */
function twig_test_iterable($value)
{
    return $value instanceof Traversable || is_array($value);
}
/**
 * Renders a template.
 *
 * @param string|array $template       The template to render or an array of templates to try consecutively
 * @param array        $variables      The variables to pass to the template
 * @param Boolean      $with_context   Whether to pass the current context variables or not
 * @param Boolean      $ignore_missing Whether to ignore missing templates or not
 * @param Boolean      $sandboxed      Whether to sandbox the template or not
 *
 * @return string The rendered template
 */
function twig_include(Twig_Environment $env, $context, $template, $variables = array(), $withContext = true, $ignoreMissing = false, $sandboxed = false)
{
    if ($withContext) {
        $variables = array_merge($context, $variables);
    }
    if ($isSandboxed = $sandboxed && $env->hasExtension('sandbox')) {
        $sandbox = $env->getExtension('sandbox');
        if (!$alreadySandboxed = $sandbox->isSandboxed()) {
            $sandbox->enableSandbox();
        }
    }
    try {
        return $env->resolveTemplate($template)->render($variables);
    } catch (Twig_Error_Loader $e) {
        if (!$ignoreMissing) {
            throw $e;
        }
    }
    if ($isSandboxed && !$alreadySandboxed) {
        $sandbox->disableSandbox();
    }
}
/**
 * Returns a template content without rendering it.
 *
 * @param string $name The template name
 *
 * @return string The template source
 */
function twig_source(Twig_Environment $env, $name)
{
    return $env->getLoader()->getSource($name);
}
/**
 * Provides the ability to get constants from instances as well as class/global constants.
 *
 * @param string      $constant The name of the constant
 * @param null|object $object   The object to get the constant from
 *
 * @return string
 */
function twig_constant($constant, $object = null)
{
    if (null !== $object) {
        $constant = get_class($object).'::'.$constant;
    }
    return constant($constant);
}
/**
 * Batches item.
 *
 * @param array   $items An array of items
 * @param integer $size  The size of the batch
 * @param mixed   $fill  A value used to fill missing items
 *
 * @return array
 */
function twig_array_batch($items, $size, $fill = null)
{
    if (is_object($items) && $items instanceof Traversable) {
        $items = iterator_to_array($items, false);
    }
    $size = ceil($size);
    $result = array_chunk($items, $size, true);
    if (null !== $fill) {
        $last = count($result) - 1;
        if ($fillCount = $size - count($result[$last])) {
            $result[$last] = array_merge(
                $result[$last],
                array_fill(0, $fillCount, $fill)
            );
        }
    }
    return $result;
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Escaper extends Twig_Extension
{
    protected $defaultStrategy;
    public function __construct($defaultStrategy = 'html')
    {
        $this->setDefaultStrategy($defaultStrategy);
    }
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    public function getTokenParsers()
    {
        return array(new Twig_TokenParser_AutoEscape());
    }
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return Twig_NodeVisitorInterface[] An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        return array(new Twig_NodeVisitor_Escaper());
    }
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('raw', 'twig_raw_filter', array('is_safe' => array('all'))),
        );
    }
    /**
     * Sets the default strategy to use when not defined by the user.
     *
     * The strategy can be a valid PHP callback that takes the template
     * "filename" as an argument and returns the strategy to use.
     *
     * @param mixed $defaultStrategy An escaping strategy
     */
    public function setDefaultStrategy($defaultStrategy)
    {
        // for BC
        if (true === $defaultStrategy) {
            $defaultStrategy = 'html';
        }
        $this->defaultStrategy = $defaultStrategy;
    }
    /**
     * Gets the default strategy to use when not defined by the user.
     *
     * @param string $filename The template "filename"
     *
     * @return string The default strategy to use for the template
     */
    public function getDefaultStrategy($filename)
    {
        // disable string callables to avoid calling a function named html or js,
        // or any other upcoming escaping strategy
        if (!is_string($this->defaultStrategy) && is_callable($this->defaultStrategy)) {
            return call_user_func($this->defaultStrategy, $filename);
        }
        return $this->defaultStrategy;
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'escaper';
    }
}
/**
 * Marks a variable as being safe.
 *
 * @param string $string A PHP variable
 */
function twig_raw_filter($string)
{
    return $string;
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Optimizer extends Twig_Extension
{
    protected $optimizers;
    public function __construct($optimizers = -1)
    {
        $this->optimizers = $optimizers;
    }
    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors()
    {
        return array(new Twig_NodeVisitor_Optimizer($this->optimizers));
    }
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'optimizer';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface all loaders must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Twig_LoaderInterface
{
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    public function getSource($name);
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    public function getCacheKey($name);
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     *
     * @return Boolean true if the template is fresh, false otherwise
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    public function isFresh($name, $time);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Marks a content as safe.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Markup implements Countable
{
    protected $content;
    protected $charset;
    public function __construct($content, $charset)
    {
        $this->content = (string) $content;
        $this->charset = $charset;
    }
    public function __toString()
    {
        return $this->content;
    }
    public function count()
    {
        return function_exists('mb_get_info') ? mb_strlen($this->content, $this->charset) : strlen($this->content);
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Default base class for compiled templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_Template implements Twig_TemplateInterface
{
    protected static $cache = array();
    protected $parent;
    protected $parents;
    protected $env;
    protected $blocks;
    protected $traits;
    /**
     * Constructor.
     *
     * @param Twig_Environment $env A Twig_Environment instance
     */
    public function __construct(Twig_Environment $env)
    {
        $this->env = $env;
        $this->blocks = array();
        $this->traits = array();
    }
    /**
     * Returns the template name.
     *
     * @return string The template name
     */
    abstract public function getTemplateName();
    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->env;
    }
    /**
     * Returns the parent template.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return Twig_TemplateInterface|false The parent template or false if there is no parent
     */
    public function getParent(array $context)
    {
        if (null !== $this->parent) {
            return $this->parent;
        }
        $parent = $this->doGetParent($context);
        if (false === $parent) {
            return false;
        } elseif ($parent instanceof Twig_Template) {
            $name = $parent->getTemplateName();
            $this->parents[$name] = $parent;
            $parent = $name;
        } elseif (!isset($this->parents[$parent])) {
            $this->parents[$parent] = $this->env->loadTemplate($parent);
        }
        return $this->parents[$parent];
    }
    protected function doGetParent(array $context)
    {
        return false;
    }
    public function isTraitable()
    {
        return true;
    }
    /**
     * Displays a parent block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to display from the parent
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     */
    public function displayParentBlock($name, array $context, array $blocks = array())
    {
        $name = (string) $name;
        if (isset($this->traits[$name])) {
            $this->traits[$name][0]->displayBlock($name, $context, $blocks);
        } elseif (false !== $parent = $this->getParent($context)) {
            $parent->displayBlock($name, $context, $blocks);
        } else {
            throw new Twig_Error_Runtime(sprintf('The template has no parent and no traits defining the "%s" block', $name), -1, $this->getTemplateName());
        }
    }
    /**
     * Displays a block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to display
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     */
    public function displayBlock($name, array $context, array $blocks = array())
    {
        $name = (string) $name;
        $template = null;
        if (isset($blocks[$name])) {
            $template = $blocks[$name][0];
            $block = $blocks[$name][1];
            unset($blocks[$name]);
        } elseif (isset($this->blocks[$name])) {
            $template = $this->blocks[$name][0];
            $block = $this->blocks[$name][1];
        }
        if (null !== $template) {
            try {
                $template->$block($context, $blocks);
            } catch (Twig_Error $e) {
                throw $e;
            } catch (Exception $e) {
                throw new Twig_Error_Runtime(sprintf('An exception has been thrown during the rendering of a template ("%s").', $e->getMessage()), -1, $template->getTemplateName(), $e);
            }
        } elseif (false !== $parent = $this->getParent($context)) {
            $parent->displayBlock($name, $context, array_merge($this->blocks, $blocks));
        }
    }
    /**
     * Renders a parent block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to render from the parent
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     *
     * @return string The rendered block
     */
    public function renderParentBlock($name, array $context, array $blocks = array())
    {
        ob_start();
        $this->displayParentBlock($name, $context, $blocks);
        return ob_get_clean();
    }
    /**
     * Renders a block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to render
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     *
     * @return string The rendered block
     */
    public function renderBlock($name, array $context, array $blocks = array())
    {
        ob_start();
        $this->displayBlock($name, $context, $blocks);
        return ob_get_clean();
    }
    /**
     * Returns whether a block exists or not.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * This method does only return blocks defined in the current template
     * or defined in "used" traits.
     *
     * It does not return blocks from parent templates as the parent
     * template name can be dynamic, which is only known based on the
     * current context.
     *
     * @param string $name The block name
     *
     * @return Boolean true if the block exists, false otherwise
     */
    public function hasBlock($name)
    {
        return isset($this->blocks[(string) $name]);
    }
    /**
     * Returns all block names.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return array An array of block names
     *
     * @see hasBlock
     */
    public function getBlockNames()
    {
        return array_keys($this->blocks);
    }
    /**
     * Returns all blocks.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return array An array of blocks
     *
     * @see hasBlock
     */
    public function getBlocks()
    {
        return $this->blocks;
    }
    /**
     * {@inheritdoc}
     */
    public function display(array $context, array $blocks = array())
    {
        $this->displayWithErrorHandling($this->env->mergeGlobals($context), $blocks);
    }
    /**
     * {@inheritdoc}
     */
    public function render(array $context)
    {
        $level = ob_get_level();
        ob_start();
        try {
            $this->display($context);
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return ob_get_clean();
    }
    protected function displayWithErrorHandling(array $context, array $blocks = array())
    {
        try {
            $this->doDisplay($context, $blocks);
        } catch (Twig_Error $e) {
            if (!$e->getTemplateFile()) {
                $e->setTemplateFile($this->getTemplateName());
            }
            // this is mostly useful for Twig_Error_Loader exceptions
            // see Twig_Error_Loader
            if (false === $e->getTemplateLine()) {
                $e->setTemplateLine(-1);
                $e->guess();
            }
            throw $e;
        } catch (Exception $e) {
            throw new Twig_Error_Runtime(sprintf('An exception has been thrown during the rendering of a template ("%s").', $e->getMessage()), -1, $this->getTemplateName(), $e);
        }
    }
    /**
     * Auto-generated method to display the template with the given context.
     *
     * @param array $context An array of parameters to pass to the template
     * @param array $blocks  An array of blocks to pass to the template
     */
    abstract protected function doDisplay(array $context, array $blocks = array());
    /**
     * Returns a variable from the context.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * This method should not be overridden in a sub-class as this is an
     * implementation detail that has been introduced to optimize variable
     * access for versions of PHP before 5.4. This is not a way to override
     * the way to get a variable value.
     *
     * @param array   $context           The context
     * @param string  $item              The variable to return from the context
     * @param Boolean $ignoreStrictCheck Whether to ignore the strict variable check or not
     *
     * @return The content of the context variable
     *
     * @throws Twig_Error_Runtime if the variable does not exist and Twig is running in strict mode
     */
    final protected function getContext($context, $item, $ignoreStrictCheck = false)
    {
        if (!array_key_exists($item, $context)) {
            if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                return null;
            }
            throw new Twig_Error_Runtime(sprintf('Variable "%s" does not exist', $item), -1, $this->getTemplateName());
        }
        return $context[$item];
    }
    /**
     * Returns the attribute value for a given array/object.
     *
     * @param mixed   $object            The object or array from where to get the item
     * @param mixed   $item              The item to get from the array or object
     * @param array   $arguments         An array of arguments to pass if the item is an object method
     * @param string  $type              The type of attribute (@see Twig_Template constants)
     * @param Boolean $isDefinedTest     Whether this is only a defined check
     * @param Boolean $ignoreStrictCheck Whether to ignore the strict attribute check or not
     *
     * @return mixed The attribute value, or a Boolean when $isDefinedTest is true, or null when the attribute is not set and $ignoreStrictCheck is true
     *
     * @throws Twig_Error_Runtime if the attribute does not exist and Twig is running in strict mode and $isDefinedTest is false
     */
    protected function getAttribute($object, $item, array $arguments = array(), $type = Twig_Template::ANY_CALL, $isDefinedTest = false, $ignoreStrictCheck = false)
    {
        // array
        if (Twig_Template::METHOD_CALL !== $type) {
            $arrayItem = is_bool($item) || is_float($item) ? (int) $item : $item;
            if ((is_array($object) && array_key_exists($arrayItem, $object))
                || ($object instanceof ArrayAccess && isset($object[$arrayItem]))
            ) {
                if ($isDefinedTest) {
                    return true;
                }
                return $object[$arrayItem];
            }
            if (Twig_Template::ARRAY_CALL === $type || !is_object($object)) {
                if ($isDefinedTest) {
                    return false;
                }
                if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                    return null;
                }
                if (is_object($object)) {
                    throw new Twig_Error_Runtime(sprintf('Key "%s" in object (with ArrayAccess) of type "%s" does not exist', $arrayItem, get_class($object)), -1, $this->getTemplateName());
                } elseif (is_array($object)) {
                    throw new Twig_Error_Runtime(sprintf('Key "%s" for array with keys "%s" does not exist', $arrayItem, implode(', ', array_keys($object))), -1, $this->getTemplateName());
                } elseif (Twig_Template::ARRAY_CALL === $type) {
                    throw new Twig_Error_Runtime(sprintf('Impossible to access a key ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
                } else {
                    throw new Twig_Error_Runtime(sprintf('Impossible to access an attribute ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
                }
            }
        }
        if (!is_object($object)) {
            if ($isDefinedTest) {
                return false;
            }
            if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                return null;
            }
            throw new Twig_Error_Runtime(sprintf('Impossible to invoke a method ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
        }
        $class = get_class($object);
        // object property
        if (Twig_Template::METHOD_CALL !== $type) {
            if (isset($object->$item) || array_key_exists((string) $item, $object)) {
                if ($isDefinedTest) {
                    return true;
                }
                if ($this->env->hasExtension('sandbox')) {
                    $this->env->getExtension('sandbox')->checkPropertyAllowed($object, $item);
                }
                return $object->$item;
            }
        }
        // object method
        if (!isset(self::$cache[$class]['methods'])) {
            self::$cache[$class]['methods'] = array_change_key_case(array_flip(get_class_methods($object)));
        }
        $call = false;
        $lcItem = strtolower($item);
        if (isset(self::$cache[$class]['methods'][$lcItem])) {
            $method = (string) $item;
        } elseif (isset(self::$cache[$class]['methods']['get'.$lcItem])) {
            $method = 'get'.$item;
        } elseif (isset(self::$cache[$class]['methods']['is'.$lcItem])) {
            $method = 'is'.$item;
        } elseif (isset(self::$cache[$class]['methods']['__call'])) {
            $method = (string) $item;
            $call = true;
        } else {
            if ($isDefinedTest) {
                return false;
            }
            if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                return null;
            }
            throw new Twig_Error_Runtime(sprintf('Method "%s" for object "%s" does not exist', $item, get_class($object)), -1, $this->getTemplateName());
        }
        if ($isDefinedTest) {
            return true;
        }
        if ($this->env->hasExtension('sandbox')) {
            $this->env->getExtension('sandbox')->checkMethodAllowed($object, $method);
        }
        // Some objects throw exceptions when they have __call, and the method we try
        // to call is not supported. If ignoreStrictCheck is true, we should return null.
        try {
            $ret = call_user_func_array(array($object, $method), $arguments);
        } catch (BadMethodCallException $e) {
            if ($call && ($ignoreStrictCheck || !$this->env->isStrictVariables())) {
                return null;
            }
            throw $e;
        }
        // useful when calling a template method from a template
        // this is not supported but unfortunately heavily used in the Symfony profiler
        if ($object instanceof Twig_TemplateInterface) {
            return $ret === '' ? '' : new Twig_Markup($ret, $this->env->getCharset());
        }
        return $ret;
    }
    /**
     * This method is only useful when testing Twig. Do not use it.
     */
    public static function clearCache()
    {
        self::$cache = array();
    }
}

}
 



namespace Monolog\Formatter
{


interface FormatterInterface
{
    
    public function format(array $record);

    
    public function formatBatch(array $records);
}
}
 



namespace Monolog\Formatter
{


class LineFormatter extends NormalizerFormatter
{
    const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    protected $format;

    
    public function __construct($format = null, $dateFormat = null)
    {
        $this->format = $format ?: static::SIMPLE_FORMAT;
        parent::__construct($dateFormat);
    }

    
    public function format(array $record)
    {
        $vars = parent::format($record);

        $output = $this->format;
        foreach ($vars['extra'] as $var => $val) {
            if (false !== strpos($output, '%extra.'.$var.'%')) {
                $output = str_replace('%extra.'.$var.'%', $this->convertToString($val), $output);
                unset($vars['extra'][$var]);
            }
        }
        foreach ($vars as $var => $val) {
            if (false !== strpos($output, '%'.$var.'%')) {
                $output = str_replace('%'.$var.'%', $this->convertToString($val), $output);
            }
        }

        return $output;
    }

    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    protected function normalize($data)
    {
        if (is_bool($data) || is_null($data)) {
            return var_export($data, true);
        }

        if ($data instanceof \Exception) {
            $previousText = '';
            if ($previous = $data->getPrevious()) {
                do {
                    $previousText .= ', '.get_class($previous).': '.$previous->getMessage().' at '.$previous->getFile().':'.$previous->getLine();
                } while ($previous = $previous->getPrevious());
            }

            return '[object] ('.get_class($data).': '.$data->getMessage().' at '.$data->getFile().':'.$data->getLine().$previousText.')';
        }

        return parent::normalize($data);
    }

    protected function convertToString($data)
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        $data = $this->normalize($data);
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            return $this->toJson($data, true);
        }

        return str_replace('\\/', '/', @json_encode($data));
    }
}
}
 



namespace Monolog\Handler
{

use Monolog\Formatter\FormatterInterface;


interface HandlerInterface
{
    
    public function isHandling(array $record);

    
    public function handle(array $record);

    
    public function handleBatch(array $records);

    
    public function pushProcessor($callback);

    
    public function popProcessor();

    
    public function setFormatter(FormatterInterface $formatter);

    
    public function getFormatter();
}
}
 



namespace Monolog\Handler
{

use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;


abstract class AbstractHandler implements HandlerInterface
{
    protected $level = Logger::DEBUG;
    protected $bubble = true;

    
    protected $formatter;
    protected $processors = array();

    
    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        $this->level = $level;
        $this->bubble = $bubble;
    }

    
    public function isHandling(array $record)
    {
        return $record['level'] >= $this->level;
    }

    
    public function handleBatch(array $records)
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    
    public function close()
    {
    }

    
    public function pushProcessor($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }
        array_unshift($this->processors, $callback);

        return $this;
    }

    
    public function popProcessor()
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;

        return $this;
    }

    
    public function getFormatter()
    {
        if (!$this->formatter) {
            $this->formatter = $this->getDefaultFormatter();
        }

        return $this->formatter;
    }

    
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    
    public function getLevel()
    {
        return $this->level;
    }

    
    public function setBubble($bubble)
    {
        $this->bubble = $bubble;

        return $this;
    }

    
    public function getBubble()
    {
        return $this->bubble;
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Exception $e) {
                    }
    }

    
    protected function getDefaultFormatter()
    {
        return new LineFormatter();
    }
}
}
 



namespace Monolog\Handler
{


abstract class AbstractProcessingHandler extends AbstractHandler
{
    
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        $record['formatted'] = $this->getFormatter()->format($record);

        $this->write($record);

        return false === $this->bubble;
    }

    
    abstract protected function write(array $record);

    
    protected function processRecord(array $record)
    {
        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        return $record;
    }
}
}
 



namespace Monolog\Handler
{

use Monolog\Logger;


class StreamHandler extends AbstractProcessingHandler
{
    protected $stream;
    protected $url;
    private $errorMessage;

    
    public function __construct($stream, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        if (is_resource($stream)) {
            $this->stream = $stream;
        } else {
            $this->url = $stream;
        }
    }

    
    public function close()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    
    protected function write(array $record)
    {
        if (null === $this->stream) {
            if (!$this->url) {
                throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
            }
            $this->errorMessage = null;
            set_error_handler(array($this, 'customErrorHandler'));
            $this->stream = fopen($this->url, 'a');
            restore_error_handler();
            if (!is_resource($this->stream)) {
                $this->stream = null;
                throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '.$this->errorMessage, $this->url));
            }
        }
        fwrite($this->stream, (string) $record['formatted']);
    }

    private function customErrorHandler($code, $msg) {
        $this->errorMessage = preg_replace('{^fopen\(.*?\): }', '', $msg);
    }
}
}
 



namespace Monolog\Handler
{

use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\FingersCrossed\ActivationStrategyInterface;
use Monolog\Logger;


class FingersCrossedHandler extends AbstractHandler
{
    protected $handler;
    protected $activationStrategy;
    protected $buffering = true;
    protected $bufferSize;
    protected $buffer = array();
    protected $stopBuffering;

    
    public function __construct($handler, $activationStrategy = null, $bufferSize = 0, $bubble = true, $stopBuffering = true)
    {
        if (null === $activationStrategy) {
            $activationStrategy = new ErrorLevelActivationStrategy(Logger::WARNING);
        }

                if (!$activationStrategy instanceof ActivationStrategyInterface) {
            $activationStrategy = new ErrorLevelActivationStrategy($activationStrategy);
        }

        $this->handler = $handler;
        $this->activationStrategy = $activationStrategy;
        $this->bufferSize = $bufferSize;
        $this->bubble = $bubble;
        $this->stopBuffering = $stopBuffering;
    }

    
    public function isHandling(array $record)
    {
        return true;
    }

    
    public function handle(array $record)
    {
        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = call_user_func($processor, $record);
            }
        }

        if ($this->buffering) {
            $this->buffer[] = $record;
            if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
                array_shift($this->buffer);
            }
            if ($this->activationStrategy->isHandlerActivated($record)) {
                if ($this->stopBuffering) {
                    $this->buffering = false;
                }
                if (!$this->handler instanceof HandlerInterface) {
                    if (!is_callable($this->handler)) {
                        throw new \RuntimeException("The given handler (".json_encode($this->handler).") is not a callable nor a Monolog\Handler\HandlerInterface object");
                    }
                    $this->handler = call_user_func($this->handler, $record, $this);
                    if (!$this->handler instanceof HandlerInterface) {
                        throw new \RuntimeException("The factory callable should return a HandlerInterface");
                    }
                }
                $this->handler->handleBatch($this->buffer);
                $this->buffer = array();
            }
        } else {
            $this->handler->handle($record);
        }

        return false === $this->bubble;
    }

    
    public function reset()
    {
        $this->buffering = true;
    }
}
}
 



namespace Monolog\Handler
{

use Monolog\Logger;


class TestHandler extends AbstractProcessingHandler
{
    protected $records = array();
    protected $recordsByLevel = array();

    public function getRecords()
    {
        return $this->records;
    }

    public function hasEmergency($record)
    {
        return $this->hasRecord($record, Logger::EMERGENCY);
    }

    public function hasAlert($record)
    {
        return $this->hasRecord($record, Logger::ALERT);
    }

    public function hasCritical($record)
    {
        return $this->hasRecord($record, Logger::CRITICAL);
    }

    public function hasError($record)
    {
        return $this->hasRecord($record, Logger::ERROR);
    }

    public function hasWarning($record)
    {
        return $this->hasRecord($record, Logger::WARNING);
    }

    public function hasNotice($record)
    {
        return $this->hasRecord($record, Logger::NOTICE);
    }

    public function hasInfo($record)
    {
        return $this->hasRecord($record, Logger::INFO);
    }

    public function hasDebug($record)
    {
        return $this->hasRecord($record, Logger::DEBUG);
    }

    public function hasEmergencyRecords()
    {
        return isset($this->recordsByLevel[Logger::EMERGENCY]);
    }

    public function hasAlertRecords()
    {
        return isset($this->recordsByLevel[Logger::ALERT]);
    }

    public function hasCriticalRecords()
    {
        return isset($this->recordsByLevel[Logger::CRITICAL]);
    }

    public function hasErrorRecords()
    {
        return isset($this->recordsByLevel[Logger::ERROR]);
    }

    public function hasWarningRecords()
    {
        return isset($this->recordsByLevel[Logger::WARNING]);
    }

    public function hasNoticeRecords()
    {
        return isset($this->recordsByLevel[Logger::NOTICE]);
    }

    public function hasInfoRecords()
    {
        return isset($this->recordsByLevel[Logger::INFO]);
    }

    public function hasDebugRecords()
    {
        return isset($this->recordsByLevel[Logger::DEBUG]);
    }

    protected function hasRecord($record, $level)
    {
        if (!isset($this->recordsByLevel[$level])) {
            return false;
        }

        if (is_array($record)) {
            $record = $record['message'];
        }

        foreach ($this->recordsByLevel[$level] as $rec) {
            if ($rec['message'] === $record) {
                return true;
            }
        }

        return false;
    }

    
    protected function write(array $record)
    {
        $this->recordsByLevel[$record['level']][] = $record;
        $this->records[] = $record;
    }
}
}
 



namespace Monolog
{

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\InvalidArgumentException;


class Logger implements LoggerInterface
{
    
    const DEBUG = 100;

    
    const INFO = 200;

    
    const NOTICE = 250;

    
    const WARNING = 300;

    
    const ERROR = 400;

    
    const CRITICAL = 500;

    
    const ALERT = 550;

    
    const EMERGENCY = 600;

    
    const API = 1;

    
    protected static $levels = array(
        100 => 'DEBUG',
        200 => 'INFO',
        250 => 'NOTICE',
        300 => 'WARNING',
        400 => 'ERROR',
        500 => 'CRITICAL',
        550 => 'ALERT',
        600 => 'EMERGENCY',
    );

    
    protected static $timezone;

    protected $name;

    
    protected $handlers;

    
    protected $processors;

    
    public function __construct($name, array $handlers = array(), array $processors = array())
    {
        $this->name = $name;
        $this->handlers = $handlers;
        $this->processors = $processors;
    }

    
    public function getName()
    {
        return $this->name;
    }

    
    public function pushHandler(HandlerInterface $handler)
    {
        array_unshift($this->handlers, $handler);
    }

    
    public function popHandler()
    {
        if (!$this->handlers) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }

        return array_shift($this->handlers);
    }

    
    public function pushProcessor($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Processors must be valid callables (callback or object with an __invoke method), '.var_export($callback, true).' given');
        }
        array_unshift($this->processors, $callback);
    }

    
    public function popProcessor()
    {
        if (!$this->processors) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    
    public function addRecord($level, $message, array $context = array())
    {
        if (!$this->handlers) {
            $this->pushHandler(new StreamHandler('php://stderr', static::DEBUG));
        }

        if (!static::$timezone) {
            static::$timezone = new \DateTimeZone(date_default_timezone_get() ?: 'UTC');
        }

        $record = array(
            'message' => (string) $message,
            'context' => $context,
            'level' => $level,
            'level_name' => static::getLevelName($level),
            'channel' => $this->name,
            'datetime' => \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)), static::$timezone)->setTimezone(static::$timezone),
            'extra' => array(),
        );
                $handlerKey = null;
        foreach ($this->handlers as $key => $handler) {
            if ($handler->isHandling($record)) {
                $handlerKey = $key;
                break;
            }
        }
                if (null === $handlerKey) {
            return false;
        }

                foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }
        while (isset($this->handlers[$handlerKey]) &&
            false === $this->handlers[$handlerKey]->handle($record)) {
            $handlerKey++;
        }

        return true;
    }

    
    public function addDebug($message, array $context = array())
    {
        return $this->addRecord(static::DEBUG, $message, $context);
    }

    
    public function addInfo($message, array $context = array())
    {
        return $this->addRecord(static::INFO, $message, $context);
    }

    
    public function addNotice($message, array $context = array())
    {
        return $this->addRecord(static::NOTICE, $message, $context);
    }

    
    public function addWarning($message, array $context = array())
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    
    public function addError($message, array $context = array())
    {
        return $this->addRecord(static::ERROR, $message, $context);
    }

    
    public function addCritical($message, array $context = array())
    {
        return $this->addRecord(static::CRITICAL, $message, $context);
    }

    
    public function addAlert($message, array $context = array())
    {
        return $this->addRecord(static::ALERT, $message, $context);
    }

    
    public function addEmergency($message, array $context = array())
    {
      return $this->addRecord(static::EMERGENCY, $message, $context);
    }

    
    public static function getLevels()
    {
        return array_flip(static::$levels);
    }

    
    public static function getLevelName($level)
    {
        if (!isset(static::$levels[$level])) {
            throw new InvalidArgumentException('Level "'.$level.'" is not defined, use one of: '.implode(', ', array_keys(static::$levels)));
        }

        return static::$levels[$level];
    }

    
    public function isHandling($level)
    {
        $record = array(
            'level' => $level,
        );

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                return true;
            }
        }

        return false;
    }

    
    public function log($level, $message, array $context = array())
    {
        if (is_string($level) && defined(__CLASS__.'::'.strtoupper($level))) {
            $level = constant(__CLASS__.'::'.strtoupper($level));
        }

        return $this->addRecord($level, $message, $context);
    }

    
    public function debug($message, array $context = array())
    {
        return $this->addRecord(static::DEBUG, $message, $context);
    }

    
    public function info($message, array $context = array())
    {
        return $this->addRecord(static::INFO, $message, $context);
    }

    
    public function notice($message, array $context = array())
    {
        return $this->addRecord(static::NOTICE, $message, $context);
    }

    
    public function warn($message, array $context = array())
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    
    public function warning($message, array $context = array())
    {
        return $this->addRecord(static::WARNING, $message, $context);
    }

    
    public function err($message, array $context = array())
    {
        return $this->addRecord(static::ERROR, $message, $context);
    }

    
    public function error($message, array $context = array())
    {
        return $this->addRecord(static::ERROR, $message, $context);
    }

    
    public function crit($message, array $context = array())
    {
        return $this->addRecord(static::CRITICAL, $message, $context);
    }

    
    public function critical($message, array $context = array())
    {
        return $this->addRecord(static::CRITICAL, $message, $context);
    }

    
    public function alert($message, array $context = array())
    {
        return $this->addRecord(static::ALERT, $message, $context);
    }

    
    public function emerg($message, array $context = array())
    {
        return $this->addRecord(static::EMERGENCY, $message, $context);
    }

    
    public function emergency($message, array $context = array())
    {
        return $this->addRecord(static::EMERGENCY, $message, $context);
    }
}
}
 



namespace Symfony\Bridge\Monolog
{

use Monolog\Logger as BaseLogger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;


class Logger extends BaseLogger implements LoggerInterface, DebugLoggerInterface
{
    
    public function emerg($message, array $context = array())
    {
        return parent::addRecord(BaseLogger::EMERGENCY, $message, $context);
    }

    
    public function crit($message, array $context = array())
    {
        return parent::addRecord(BaseLogger::CRITICAL, $message, $context);
    }

    
    public function err($message, array $context = array())
    {
        return parent::addRecord(BaseLogger::ERROR, $message, $context);
    }

    
    public function warn($message, array $context = array())
    {
        return parent::addRecord(BaseLogger::WARNING, $message, $context);
    }

    
    public function getLogs()
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->getLogs();
        }

        return array();
    }

    
    public function countErrors()
    {
        if ($logger = $this->getDebugLogger()) {
            return $logger->countErrors();
        }

        return 0;
    }

    
    private function getDebugLogger()
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof DebugLoggerInterface) {
                return $handler;
            }
        }
    }
}
}
 



namespace Symfony\Bridge\Monolog\Handler
{

use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;


class DebugHandler extends TestHandler implements DebugLoggerInterface
{
    
    public function getLogs()
    {
        $records = array();
        foreach ($this->records as $record) {
            $records[] = array(
                'timestamp'    => $record['datetime']->getTimestamp(),
                'message'      => $record['message'],
                'priority'     => $record['level'],
                'priorityName' => $record['level_name'],
                'context'      => $record['context'],
            );
        }

        return $records;
    }

    
    public function countErrors()
    {
        $cnt = 0;
        $levels = array(Logger::ERROR, Logger::CRITICAL, Logger::ALERT, Logger::EMERGENCY);
        foreach ($levels as $level) {
            if (isset($this->recordsByLevel[$level])) {
                $cnt += count($this->recordsByLevel[$level]);
            }
        }

        return $cnt;
    }
}
}
 



namespace Monolog\Handler\FingersCrossed
{


interface ActivationStrategyInterface
{
    
    public function isHandlerActivated(array $record);
}
}
 



namespace Monolog\Handler\FingersCrossed
{


class ErrorLevelActivationStrategy implements ActivationStrategyInterface
{
    private $actionLevel;

    public function __construct($actionLevel)
    {
        $this->actionLevel = $actionLevel;
    }

    public function isHandlerActivated(array $record)
    {
        return $record['level'] >= $this->actionLevel;
    }
}
}
 



namespace Symfony\Bundle\AsseticBundle
{

use Assetic\ValueSupplierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class DefaultValueSupplier implements ValueSupplierInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getValues()
    {
        if (!$this->container->isScopeActive('request')) {
            return array();
        }

        $request = $this->container->get('request');

        return array(
            'locale' => $request->getLocale(),
            'env'    => $this->container->getParameter('kernel.environment'),
        );
    }
}
}
 



namespace Symfony\Bundle\AsseticBundle\Factory
{

use Assetic\Factory\AssetFactory as BaseAssetFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;


class AssetFactory extends BaseAssetFactory
{
    private $kernel;
    private $container;
    private $parameterBag;

    
    public function __construct(KernelInterface $kernel, ContainerInterface $container, ParameterBagInterface $parameterBag, $baseDir, $debug = false)
    {
        $this->kernel = $kernel;
        $this->container = $container;
        $this->parameterBag = $parameterBag;

        parent::__construct($baseDir, $debug);
    }

    
    protected function parseInput($input, array $options = array())
    {
        $input = $this->parameterBag->resolveValue($input);

                if ('@' == $input[0] && false !== strpos($input, '/')) {
                        $bundle = substr($input, 1);
            if (false !== $pos = strpos($bundle, '/')) {
                $bundle = substr($bundle, 0, $pos);
            }
            $options['root'] = array($this->kernel->getBundle($bundle)->getPath());

                        if (false !== $pos = strpos($input, '*')) {
                                list($before, $after) = explode('*', $input, 2);
                $input = $this->kernel->locateResource($before).'*'.$after;
            } else {
                $input = $this->kernel->locateResource($input);
            }
        }

        return parent::parseInput($input, $options);
    }

    protected function createAssetReference($name)
    {
        if (!$this->getAssetManager()) {
            $this->setAssetManager($this->container->get('assetic.asset_manager'));
        }

        return parent::createAssetReference($name);
    }

    protected function getFilter($name)
    {
        if (!$this->getFilterManager()) {
            $this->setFilterManager($this->container->get('assetic.filter_manager'));
        }

        return parent::getFilter($name);
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
use Doctrine\Common\Util\ClassUtils;




class ControllerListener implements EventSubscriberInterface
{
    
    protected $reader;

    
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $className = class_exists('Doctrine\Common\Util\ClassUtils') ? ClassUtils::getClass($controller[0]) : get_class($controller[0]);
        $object    = new \ReflectionClass($className);
        $method    = $object->getMethod($controller[1]);

        $classConfigurations  = $this->getConfigurations($this->reader->getClassAnnotations($object));
        $methodConfigurations = $this->getConfigurations($this->reader->getMethodAnnotations($method));

        $configurations = array();
        foreach (array_merge(array_keys($classConfigurations), array_keys($methodConfigurations)) as $key) {
            if (!array_key_exists($key, $classConfigurations)) {
                $configurations[$key] = $methodConfigurations[$key];
            } elseif (!array_key_exists($key, $methodConfigurations)) {
                $configurations[$key] = $classConfigurations[$key];
            } else {
                if (is_array($classConfigurations[$key])) {
                    if (!is_array($methodConfigurations[$key])) {
                        throw new \UnexpectedValueException('Configurations should both be an array or both not be an array');
                    }
                    $configurations[$key] = array_merge($classConfigurations[$key], $methodConfigurations[$key]);
                } else {
                                        $configurations[$key] = $methodConfigurations[$key];
                }
            }
        }

        $request = $event->getRequest();
        foreach ($configurations as $key => $attributes) {
            $request->attributes->set($key, $attributes);
        }
    }

    protected function getConfigurations(array $annotations)
    {
        $configurations = array();
        foreach ($annotations as $configuration) {
            if ($configuration instanceof ConfigurationInterface) {
                if ($configuration->allowArray()) {
                    $configurations['_'.$configuration->getAliasName()][] = $configuration;
                } else {
                    $configurations['_'.$configuration->getAliasName()] = $configuration;
                }
            }
        }

        return $configurations;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
        );
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;




class ParamConverterListener implements EventSubscriberInterface
{
    
    protected $manager;

    
    public function __construct(ParamConverterManager $manager)
    {
        $this->manager = $manager;
    }

    
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        $request = $event->getRequest();
        $configurations = array();

        if ($configuration = $request->attributes->get('_converters')) {
            foreach (is_array($configuration) ? $configuration : array($configuration) as $configuration) {
                $configurations[$configuration->getName()] = $configuration;
            }
        }

        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } else {
            $r = new \ReflectionFunction($controller);
        }

                foreach ($r->getParameters() as $param) {
            if (!$param->getClass() || $param->getClass()->isInstance($request)) {
                continue;
            }

            $name = $param->getName();

            if (!isset($configurations[$name])) {
                $configuration = new ParamConverter(array());
                $configuration->setName($name);
                $configuration->setClass($param->getClass()->getName());

                $configurations[$name] = $configuration;
            } elseif (null === $configurations[$name]->getClass()) {
                $configurations[$name]->setClass($param->getClass()->getName());
            }

            $configurations[$name]->setIsOptional($param->isOptional());
        }

        $this->manager->apply($request, $configurations);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
        );
    }
}
}
 



namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTime;


class DateTimeParamConverter implements ParamConverterInterface
{
    
    public function apply(Request $request, ParamConverter $configuration)
    {
        $param = $configuration->getName();

        if (!$request->attributes->has($param)) {
            return false;
        }

        $options = $configuration->getOptions();
        $value   = $request->attributes->get($param);

        if (!$value && $configuration->isOptional()) {
            return false;
        }

        $date = isset($options['format'])
            ? DateTime::createFromFormat($options['format'], $value)
            : new DateTime($value);

        if (!$date) {
            throw new NotFoundHttpException('Invalid date given.');
        }

        $request->attributes->set($param, $date);

        return true;
    }

    
    public function supports(ParamConverter $configuration)
    {
        if (null === $configuration->getClass()) {
            return false;
        }

        return "DateTime" === $configuration->getClass();
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;




class DoctrineParamConverter implements ParamConverterInterface
{
    
    protected $registry;

    public function __construct(ManagerRegistry $registry = null)
    {
        $this->registry = $registry;
    }

    
    public function apply(Request $request, ParamConverter $configuration)
    {
        $name    = $configuration->getName();
        $class   = $configuration->getClass();
        $options = $this->getOptions($configuration);

        if (null === $request->attributes->get($name, false)) {
            $configuration->setIsOptional(true);
        }

                if (false === $object = $this->find($class, $request, $options, $name)) {
                        if (false === $object = $this->findOneBy($class, $request, $options)) {
                if ($configuration->isOptional()) {
                    $object = null;
                } else {
                    throw new \LogicException('Unable to guess how to get a Doctrine instance from the request information.');
                }
            }
        }

        if (null === $object && false === $configuration->isOptional()) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($name, $object);

        return true;
    }

    protected function find($class, Request $request, $options, $name)
    {
        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);

        if (false === $id || null === $id) {
            return false;
        }

        if (isset($options['repository_method'])) {
            $method = $options['repository_method'];
        } else {
            $method = 'find';
        }

        try {
            return $this->getManager($options['entity_manager'], $class)->getRepository($class)->$method($id);
        } catch (NoResultException $e) {
            return null;
        }
    }

    protected function getIdentifier(Request $request, $options, $name)
    {
        if (isset($options['id'])) {
            if (!is_array($options['id'])) {
                $name = $options['id'];
            } elseif (is_array($options['id'])) {
                $id = array();
                foreach ($options['id'] as $field) {
                    $id[$field] = $request->attributes->get($field);
                }

                return $id;
            }
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name);
        }

        if ($request->attributes->has('id')) {
            return $request->attributes->get('id');
        }

        return false;
    }

    protected function findOneBy($class, Request $request, $options)
    {
        if (!$options['mapping']) {
            $keys               = $request->attributes->keys();
            $options['mapping'] = $keys ? array_combine($keys, $keys) : array();
        }

        foreach ($options['exclude'] as $exclude) {
            unset($options['mapping'][$exclude]);
        }

        if (!$options['mapping']) {
            return false;
        }

        $criteria = array();
        $em = $this->getManager($options['entity_manager'], $class);
        $metadata = $em->getClassMetadata($class);

        foreach ($options['mapping'] as $attribute => $field) {
            if ($metadata->hasField($field) || ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field))) {
                $criteria[$field] = $request->attributes->get($attribute);
            }
        }

        if ($options['strip_null']) {
            $criteria = array_filter($criteria, function ($value) { return !is_null($value); });
        }

        if (!$criteria) {
            return false;
        }

        if (isset($options['repository_method'])) {
            $method = $options['repository_method'];
        } else {
            $method = 'findOneBy';
        }

        try {
            return $em->getRepository($class)->$method($criteria);
        } catch (NoResultException $e) {
            return null;
        }
    }

    
    public function supports(ParamConverter $configuration)
    {
                if (null === $this->registry || !count($this->registry->getManagers())) {
            return false;
        }

        if (null === $configuration->getClass()) {
            return false;
        }

        $options = $this->getOptions($configuration);

                $em = $this->getManager($options['entity_manager'], $configuration->getClass());
        if (null === $em) {
            return false;
        }

        return ! $em->getMetadataFactory()->isTransient($configuration->getClass());
    }

    protected function getOptions(ParamConverter $configuration)
    {
        return array_replace(array(
            'entity_manager' => null,
            'exclude'        => array(),
            'mapping'        => array(),
            'strip_null'     => false,
        ), $configuration->getOptions());
    }

    private function getManager($name, $class)
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        return $this->registry->getManager($name);
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;




interface ParamConverterInterface
{
    
    public function apply(Request $request, ParamConverter $configuration);

    
    public function supports(ParamConverter $configuration);
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter
{

use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;




class ParamConverterManager
{
    
    protected $converters = array();

    
    protected $namedConverters = array();

    
    public function apply(Request $request, $configurations)
    {
        if (is_object($configurations)) {
            $configurations = array($configurations);
        }

        foreach ($configurations as $configuration) {
            $this->applyConverter($request, $configuration);
        }
    }

    
    protected function applyConverter(Request $request, ConfigurationInterface $configuration)
    {
        $value     = $request->attributes->get($configuration->getName());
        $className = $configuration->getClass();

                        if (is_object($value) && $value instanceof $className) {
            return;
        }

        if ($converterName = $configuration->getConverter()) {
            if (!isset($this->namedConverters[$converterName])) {
                throw new \RuntimeException(sprintf(
                    "No converter named '%s' found for conversion of parameter '%s'.",
                    $converterName, $configuration->getName()
                ));
            }

            $converter = $this->namedConverters[$converterName];

            if (!$converter->supports($configuration)) {
                throw new \RuntimeException(sprintf(
                    "Converter '%s' does not support conversion of parameter '%s'.",
                    $converterName, $configuration->getName()
                ));
            }

            $converter->apply($request, $configuration);

            return;
        }

        foreach ($this->all() as $converter) {
            if ($converter->supports($configuration)) {
                if ($converter->apply($request, $configuration)) {
                    return;
                }
            }
        }
   }

   
    public function add(ParamConverterInterface $converter, $priority = 0, $name = null)
    {
        if ($priority !== null) {
            if (!isset($this->converters[$priority])) {
                $this->converters[$priority] = array();
            }

            $this->converters[$priority][] = $converter;
        }

        if (null !== $name) {
            $this->namedConverters[$name] = $converter;
        }
    }

   
   public function all()
   {
       krsort($this->converters);

       $converters = array();
       foreach ($this->converters as $all) {
           $converters = array_merge($converters, $all);
       }

       return $converters;
   }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;




class TemplateListener implements EventSubscriberInterface
{
    
    protected $container;

    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $request = $event->getRequest();

        if (!$configuration = $request->attributes->get('_template')) {
            return;
        }

        if (!$configuration->getTemplate()) {
            $guesser = $this->container->get('sensio_framework_extra.view.guesser');
            $configuration->setTemplate($guesser->guessTemplateName($controller, $request, $configuration->getEngine()));
        }

        $request->attributes->set('_template', $configuration->getTemplate());
        $request->attributes->set('_template_vars', $configuration->getVars());
        $request->attributes->set('_template_streamable', $configuration->isStreamable());

                if (!$configuration->getVars()) {
            $r = new \ReflectionObject($controller[0]);

            $vars = array();
            foreach ($r->getMethod($controller[1])->getParameters() as $param) {
                $vars[] = $param->getName();
            }

            $request->attributes->set('_template_default_vars', $vars);
        }
    }

    
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();
        $parameters = $event->getControllerResult();
        $templating = $this->container->get('templating');

        if (null === $parameters) {
            if (!$vars = $request->attributes->get('_template_vars')) {
                if (!$vars = $request->attributes->get('_template_default_vars')) {
                    return;
                }
            }

            $parameters = array();
            foreach ($vars as $var) {
                $parameters[$var] = $request->attributes->get($var);
            }
        }

        if (!is_array($parameters)) {
            return $parameters;
        }

        if (!$template = $request->attributes->get('_template')) {
            return $parameters;
        }

        if (!$request->attributes->get('_template_streamable')) {
            $event->setResponse($templating->renderResponse($template, $parameters));
        } else {
            $callback = function () use ($templating, $template, $parameters) {
                return $templating->stream($template, $parameters);
            };

            $event->setResponse(new StreamedResponse($callback));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array('onKernelController', -128),
            KernelEvents::VIEW => 'onKernelView',
        );
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;




class HttpCacheListener implements EventSubscriberInterface
{
    private $lastModifiedDates;
    private $etags;
    private $expressionLanguage;

    public function __construct()
    {
        $this->lastModifiedDates = new \SplObjectStorage();
        $this->etags = new \SplObjectStorage();
    }

    
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if (!$configuration = $request->attributes->get('_cache')) {
            return;
        }

        $response = new Response();

        $lastModifiedDate = '';
        if ($configuration->getLastModified()) {
            $lastModifiedDate = $this->getExpressionLanguage()->evaluate($configuration->getLastModified(), $request->attributes->all());
            $response->setLastModified($lastModifiedDate);
        }

        $etag = '';
        if ($configuration->getETag()) {
            $etag = hash('sha256', $this->getExpressionLanguage()->evaluate($configuration->getETag(), $request->attributes->all()));
            $response->setETag($etag);
        }

        if ($response->isNotModified($request)) {
            $event->setController(function () use ($response) {
                return $response;
            });
        } else {
            if ($etag) {
                $this->etags[$request] = $etag;
            }
            if ($lastModifiedDate) {
                $this->lastModifiedDates[$request] = $lastModifiedDate;
            }
        }
    }

    
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$configuration = $request->attributes->get('_cache')) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        if (null !== $configuration->getSMaxAge()) {
            $response->setSharedMaxAge($configuration->getSMaxAge());
        }

        if (null !== $configuration->getMaxAge()) {
            $response->setMaxAge($configuration->getMaxAge());
        }

        if (null !== $configuration->getExpires()) {
            $date = \DateTime::createFromFormat('U', strtotime($configuration->getExpires()), new \DateTimeZone('UTC'));
            $response->setExpires($date);
        }

        if (null !== $configuration->getVary()) {
            $response->setVary($configuration->getVary());
        }

        if ($configuration->isPublic()) {
            $response->setPublic();
        }

        if (isset($this->lastModifiedDates[$request])) {
            $response->setLastModified($this->lastModifiedDates[$request]);

            unset($this->lastModifiedDates[$request]);
        }

        if (isset($this->etags[$request])) {
            $response->setETag($this->etags[$request]);

            unset($this->etags[$request]);
        }

        $event->setResponse($response);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }
}
}
 



namespace Sensio\Bundle\FrameworkExtraBundle\EventListener
{

use Sensio\Bundle\FrameworkExtraBundle\Security\ExpressionLanguage;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;


class SecurityListener implements EventSubscriberInterface
{
    private $securityContext;
    private $language;
    private $trustResolver;
    private $roleHierarchy;

    public function __construct(SecurityContextInterface $securityContext = null, ExpressionLanguage $language = null, AuthenticationTrustResolverInterface $trustResolver = null, RoleHierarchyInterface $roleHierarchy = null)
    {
        $this->securityContext = $securityContext;
        $this->language = $language;
        $this->trustResolver = $trustResolver;
        $this->roleHierarchy = $roleHierarchy;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if (!$configuration = $request->attributes->get('_security')) {
            return;
        }

        if (null === $this->securityContext || null === $this->trustResolver) {
            throw new \LogicException('To use the @Security tag, you need to install the Symfony Security bundle.');
        }

        if (!$this->language->evaluate($configuration->getExpression(), $this->getVariables($request))) {
            throw new AccessDeniedException(sprintf('Expression "%s" denied access.', $configuration->getExpression()));
        }
    }

        private function getVariables(Request $request)
    {
        $token = $this->securityContext->getToken();

        if (null !== $this->roleHierarchy) {
            $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
        } else {
            $roles = $token->getRoles();
        }

        $variables = array(
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $request,
            'request' => $request,
            'roles' => array_map(function ($role) { return $role->getRole(); }, $roles),
            'trust_resolver' => $this->trustResolver,
            'security_context' => $this->securityContext,
        );

                return array_merge($request->attributes->all(), $variables);
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::CONTROLLER => 'onKernelController');
    }
}
}
 

namespace Sensio\Bundle\FrameworkExtraBundle\Configuration
{


abstract class ConfigurationAnnotation implements ConfigurationInterface
{
    public function __construct(array $values)
    {
        foreach ($values as $k => $v) {
            if (!method_exists($this, $name = 'set'.$k)) {
                throw new \RuntimeException(sprintf('Unknown key "%s" for annotation "@%s".', $k, get_class($this)));
            }

            $this->$name($v);
        }
    }
}
}

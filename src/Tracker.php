<?php

namespace OzanKurt\Tracker;

use Illuminate\Foundation\Application as Laravel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use OzanKurt\Support\Config;
use OzanKurt\Support\GeoIp\Updater as GeoIpUpdater;
use OzanKurt\Support\Support\IpAddress;
use OzanKurt\Tracker\Data\RepositoryManager as DataRepositoryManager;
use OzanKurt\Tracker\Repositories\Message as MessageRepository;
use OzanKurt\Tracker\Support\CrawlerDetector;
use OzanKurt\Tracker\Support\Minutes;
use Psr\Log\LoggerInterface;

class Tracker
{
    protected $enabled = true;

    protected $sessionData;

    protected $loggedItems = [];

    protected $booted = false;

    public function __construct() {
    }

    public function allSessions()
    {
        return $this->dataRepositoryManager->getAllSessions();
    }

    public function boot()
    {
        if ($this->booted) {
            return false;
        }

        $this->booted = true;

        if ($this->isTrackable()) {
            $this->track();
        }

        return true;
    }

    public function checkCurrentUser()
    {
        if (!$this->sessionData['user_id'] && $user_id = $this->getUserId()) {
            return true;
        }

        return false;
    }

    public function currentSession()
    {
        return $this->dataRepositoryManager->sessionRepository->getCurrent();
    }

    protected function deleteCurrentLog()
    {
        $this->dataRepositoryManager->logRepository->delete();
    }

    public function errors($minutes, $results = true)
    {
        return $this->dataRepositoryManager->errors(Minutes::make($minutes), $results);
    }

    public function events($minutes, $results = true)
    {
        return $this->dataRepositoryManager->events(Minutes::make($minutes), $results);
    }

    public function getAgentId()
    {
        return config('tracker.log_user_agents')
            ? $this->dataRepositoryManager->getAgentId()
            : null;
    }

    public function getConfig($key)
    {
        return config($tracker.key);
    }

    public function getCookieId()
    {
        return config('tracker.store_cookie_tracker')
            ? $this->dataRepositoryManager->getCookieId()
            : null;
    }

    public function getDeviceId()
    {
        return config('tracker.log_devices')
            ? $this->dataRepositoryManager->findOrCreateDevice(
                $this->dataRepositoryManager->getCurrentDeviceProperties()
            )
            : null;
    }

    public function getLanguageId()
    {
        return config('tracker.log_languages')
            ? $this->dataRepositoryManager->findOrCreateLanguage($this->dataRepositoryManager->getCurrentLanguage())
            : null;
    }

    public function getDomainId($domain)
    {
        return $this->dataRepositoryManager->getDomainId($domain);
    }

    public function getGeoIpId()
    {
        return config('tracker.log_geoip')
            ? $this->dataRepositoryManager->getGeoIpId(request()->getClientIp())
            : null;
    }

    /**
     * @return array
     */
    public function getLogData()
    {
        return [
            'session_id' => $this->getSessionId(true),
            'method'     => request()->method(),
            'path_id'    => $this->getPathId(),
            'query_id'   => $this->getQueryId(),
            'referer_id' => $this->getRefererId(),
            'is_ajax'    => request()->ajax(),
            'is_secure'  => request()->isSecure(),
            'is_json'    => request()->isJson(),
            'wants_json' => request()->wantsJson(),
        ];
    }

    public function getLogger()
    {
        return app('log');
    }

    public function getPathId()
    {
        return config('tracker.log_paths')
            ? $this->dataRepositoryManager->findOrCreatePath(
                [
                    'path' => request()->path(),
                ]
            )
            : null;
    }

    public function getQueryId()
    {
        if (config('tracker.log_queries')) {
            if (count($arguments = request()->query())) {
                return $this->dataRepositoryManager->getQueryId(
                    [
                        'query'     => array_implode('=', '|', $arguments),
                        'arguments' => $arguments,
                    ]
                );
            }
        }
    }

    public function getRefererId()
    {
        return config('tracker.log_referers')
            ? $this->dataRepositoryManager->getRefererId(
                request()->headers->get('referer')
            )
            : null;
    }

    public function getRoutePathId()
    {
        return $this->dataRepositoryManager->getRoutePathId($this->route, request());
    }

    protected function logUntrackable($item)
    {
        if (config('tracker.log_untrackable_sessions') && !isset($this->loggedItems[$item])) {
            $this->getLogger()->warning('TRACKER (unable to track item): '.$item);

            $this->loggedItems[$item] = $item;
        }
    }

    /**
     * @return array
     */
    protected function makeSessionData()
    {
        $sessionData = [
            'user_id'      => $this->getUserId(),
            'device_id'    => $this->getDeviceId(),
            'client_ip'    => request()->getClientIp(),
            'geoip_id'     => $this->getGeoIpId(),
            'agent_id'     => $this->getAgentId(),
            'referer_id'   => $this->getRefererId(),
            'cookie_id'    => $this->getCookieId(),
            'language_id'  => $this->getLanguageId(),
            'is_robot'     => $this->isRobot(),

            // The key user_agent is not present in the sessions table, but
            // it's internally used to check if the user agent changed
            // during a session.
            'user_agent' => $this->dataRepositoryManager->getCurrentUserAgent(),
        ];

        return $this->sessionData = $this->dataRepositoryManager->checkSessionData($sessionData, $this->sessionData);
    }

    public function getSessionId($updateLastActivity = false)
    {
        dd(
            $this->makeSessionData(),
            $updateLastActivity);
        return $this->dataRepositoryManager->getSessionId(
            $this->makeSessionData(),
            $updateLastActivity
        );
    }

    public function getUserId()
    {
        return config('tracker.log_users')
            ? auth()->id()
            : null;
    }

    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable($throwable)
    {
        if (config('tracker.log_enabled')) {
            $this->dataRepositoryManager->handleThrowable($throwable);
        }
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isRobot()
    {
        $crawlerDetector = new CrawlerDetector(request()->header(), request()->header('user-agent'));

        return $crawlerDetector->isRobot();
    }

    protected function isSqlQueriesLoggableConnection($name)
    {
        return !in_array(
            $name,
            config('tracker.do_not_log_sql_queries_connections')
        );
    }

    public function isTrackable()
    {
        $isTrackable = config('tracker.enabled') &&
                $this->logIsEnabled() &&
                $this->allowConsole() &&
                $this->isTrackableIp() &&
                $this->isTrackableEnvironment() &&
                $this->routeIsTrackable() &&
                $this->pathIsTrackable() &&
                $this->notRobotOrTrackable();

        if (!$isTrackable) {
//            dd(
//                config('tracker.enabled'),
//                $this->logIsEnabled(),
//                $this->allowConsole(),
//                $this->isTrackableIp(),
//                $this->isTrackableEnvironment(),
//                $this->routeIsTrackable(),
//                $this->pathIsTrackable(),
//                $this->notRobotOrTrackable()
//            );
            return false;
        }

        return true;
    }

    public function isTrackableEnvironment()
    {
        $trackable = !in_array(
            app()->environment(),
            config('tracker.do_not_track_environments')
        );

        if (!$trackable) {
            $this->logUntrackable('environment '.app()->environment().' is not trackable.');
        }

        return $trackable;
    }

    public function isTrackableIp()
    {
        $trackable = !IpAddress::ipv4InRange(
            $ipAddress = request()->getClientIp(),
            config('tracker.do_not_track_ips')
        );

        if (!$trackable) {
            $this->logUntrackable($ipAddress.' is not trackable.');
        }

        return $trackable;
    }

    public function logByRouteName($name, $minutes = null)
    {
        if ($minutes) {
            $minutes = Minutes::make($minutes);
        }

        return $this->dataRepositoryManager->logByRouteName($name, $minutes);
    }

    public function logEvents()
    {
        if (
            $this->isTrackable() &&
            config('tracker.log_enabled') &&
            config('tracker.log_events')
        ) {
            $this->dataRepositoryManager->logEvents();
        }
    }

    public function logIsEnabled()
    {
        $enabled =
            config('tracker.log_enabled') ||
            config('tracker.log_sql_queries') ||
            config('tracker.log_sql_queries_bindings') ||
            config('tracker.log_events') ||
            config('tracker.log_geoip') ||
            config('tracker.log_user_agents') ||
            config('tracker.log_users') ||
            config('tracker.log_devices') ||
            config('tracker.log_languages') ||
            config('tracker.log_referers') ||
            config('tracker.log_paths') ||
            config('tracker.log_queries') ||
            config('tracker.log_routes') ||
            config('tracker.log_exceptions');

        if (!$enabled) {
            $this->logUntrackable('there are no log items enabled.');
        }

        return $enabled;
    }

    public function logSqlQuery($query, $bindings, $time, $name)
    {
        if (
            $this->isTrackable() &&
            config('tracker.log_enabled') &&
            config('tracker.log_sql_queries') &&
            $this->isSqlQueriesLoggableConnection($name)
        ) {
            $this->dataRepositoryManager->logSqlQuery($query, $bindings, $time, $name);
        }
    }

    protected function notRobotOrTrackable()
    {
        $trackable =
            !$this->isRobot() ||
            !config('tracker.do_not_track_robots');

        if (!$trackable) {
            $this->logUntrackable('tracking of robots is disabled.');
        }

        return $trackable;
    }

    public function pageViews($minutes, $results = true)
    {
        return $this->dataRepositoryManager->pageViews(Minutes::make($minutes), $results);
    }

    public function pageViewsByCountry($minutes, $results = true)
    {
        return $this->dataRepositoryManager->pageViewsByCountry(Minutes::make($minutes), $results);
    }

    public function allowConsole()
    {
        return (!app()->runningInConsole()) ||
            config('tracker.console_log_enabled', false);
    }

    public function routeIsTrackable()
    {
        $route = request()->route();

        if (is_null($route)) {
            return false;
        }

        $forbidden = config('tracker.do_not_track_routes');

        $trackable = !$forbidden || !$route->getName() || !in_array_wildcard($route->getName(), $forbidden);

        if (!$trackable) {
            $this->logUntrackable('route '. $route->getName().' is not trackable.');
        }

        return $trackable;
    }

    public function routerMatched($log)
    {
        if ($this->dataRepositoryManager->routeIsTrackable($this->route)) {
            if ($log) {
                $this->dataRepositoryManager->updateRoute(
                    $this->getRoutePathId()
                );
            }
        }
        // Router was matched but this route is not trackable
        // Let's just delete the stored data, because There's not a
        // realy clean way of doing this because if a route is not
        // matched, and this happens ages after the app is booted,
        // we till need to store data from the request.
        else {
            $this->turnOff();

            $this->deleteCurrentLog();
        }
    }

    public function sessionLog($uuid, $results = true)
    {
        return $this->dataRepositoryManager->getSessionLog($uuid, $results);
    }

    public function sessions($minutes = 1440, $results = true)
    {
        return $this->dataRepositoryManager->getLastSessions(Minutes::make($minutes), $results);
    }

    public function onlineUsers($minutes = 3, $results = true)
    {
        return $this->sessions(3);
    }

    public function track()
    {
        $log = $this->getLogData();

        if (config('tracker.log_enabled')) {
            $this->dataRepositoryManager->createLog($log);
        }
    }

    public function trackEvent($event)
    {
        $this->dataRepositoryManager->trackEvent($event);
    }

    public function trackVisit($route, $request)
    {
        $this->dataRepositoryManager->trackRoute($route, $request);
    }

    public function turnOff()
    {
        $this->enabled = false;
    }

    public function userDevices($minutes, $user_id = null, $results = true)
    {
        return $this->dataRepositoryManager->userDevices(
            Minutes::make($minutes),
            $user_id,
            $results
        );
    }

    public function users($minutes, $results = true)
    {
        return $this->dataRepositoryManager->users(Minutes::make($minutes), $results);
    }

    /**
     * Get the messages.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMessages()
    {
        return $this->messageRepository->getMessages();
    }

    /**
     * Update the GeoIp2 database.
     *
     * @return bool
     */
    public function updateGeoIp()
    {
        $updater = new GeoIpUpdater();

        $success = $updater->updateGeoIpFiles(config('tracker.geoip_database_path'));

        $this->messageRepository->addMessage($updater->getMessages());

        return $success;
    }

    public function pathIsTrackable()
    {
        $path = request()->path();
        $forbidden = config('tracker.do_not_track_paths');

        $trackable = !$forbidden || empty($path) || !in_array_wildcard($path, $forbidden);

        if (!$trackable) {
            $this->logUntrackable('path '.request()->path().' is not trackable.');
        }

        return $trackable;
    }
}

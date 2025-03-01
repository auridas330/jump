<?php

/**
 * TO DO:
 * - use CSRF token in weatherdata and icon api
 *
 */

namespace Jump;

use Nette\Routing\RouteList;

class Main {

    private Cache $cache;
    private Config $config;
    private \Nette\Http\Request $request;
    private \Nette\Http\Session $session;

    public function __construct() {
        $this->config = new Config();
        $this->cache = new Cache($this->config);
        $this->router = new RouteList;

        // Set up the routes that Jump expects.
        $this->router->addRoute('/tag/<param>', [
			'class' => 'Jump\Pages\TagPage'
		]);
    }

    function init() {
        // Create a request object based on globals so we can utilise url rewriting etc.
        $this->request = (new \Nette\Http\RequestFactory)->fromGlobals();

        // Initialise a new session using the request object.
        $this->session = new \Nette\Http\Session($this->request, new \Nette\Http\Response);
        $this->session->setName($this->config->get('sessionname'));
        $this->session->setExpiration($this->config->get('sessiontimeout'));

        // Get a Nette session section for CSRF data.
        $csrfsection = $this->session->getSection('csrf');

        // Create a new CSRF token within the section if one doesn't exist already.
        if (!$csrfsection->offsetExists('token')){
            $csrfsection->set('token', bin2hex(random_bytes(32)));
        }

        // Try to match the correct route based on the HTTP request.
        $matchedroute = $this->router->match($this->request);

        // If we do not have a matched route then just serve up the home page.
        $pageclass = $matchedroute['class'] ?? 'Jump\Pages\HomePage';
        $param = $matchedroute['param'] ?? null;

        // Instantiate the correct class to build the requested page, get the
        // content and return it.
        $page = new $pageclass($this->config, $this->cache, $this->session, $param ?? null);
        return $page->get_output();
    }

}

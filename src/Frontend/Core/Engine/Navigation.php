<?php

namespace Frontend\Core\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Frontend\Core\Language\Language;
use Symfony\Component\HttpKernel\KernelInterface;
use Backend\Modules\Pages\Engine\Model as BackendPagesModel;
use Frontend\Core\Engine\Base\Object as FrontendBaseObject;
use Frontend\Core\Engine\Model as FrontendModel;
use Frontend\Modules\Profiles\Engine\Authentication as FrontendAuthentication;

/**
 * This class will be used to build the navigation
 */
class Navigation extends FrontendBaseObject
{
    /**
     * The excluded page ids. These will not be shown in the menu.
     *
     * @var array
     */
    private static $excludedPageIds = array();

    /**
     * The selected pageIds
     *
     * @var array
     */
    private static $selectedPageIds = array();

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);

        // set selected ids
        $this->setSelectedPageIds();
    }

    /**
     * Creates a Backend URL for a given action and module
     * If you don't specify a language the current language will be used.
     *
     * @param string $action     The action to build the URL for.
     * @param string $module     The module to build the URL for.
     * @param string $language   The language to use, if not provided we will use the working language.
     * @param array  $parameters GET-parameters to use.
     * @param bool   $urlencode  Should the parameters be urlencoded?
     *
     * @return string
     */
    public static function getBackendURLForBlock(
        $action,
        $module,
        $language = null,
        array $parameters = null,
        $urlencode = true
    ) {
        $action = (string) $action;
        $module = (string) $module;
        $language = ($language !== null) ? (string) $language : LANGUAGE;
        $queryString = '';

        // add at least one parameter
        if (empty($parameters)) {
            $parameters['token'] = 'true';
        }

        // init counter
        $i = 1;

        // add parameters
        foreach ($parameters as $key => $value) {
            // first element
            if ($i == 1) {
                $queryString .= '?' . $key . '=' . (($urlencode) ? rawurlencode($value) : $value);
            } else {
                // other elements
                $queryString .= '&amp;' . $key . '=' . (($urlencode) ? rawurlencode($value) : $value);
            }

            // update counter
            ++$i;
        }

        // build the URL and return it
        return '/private/' . $language . '/' . $module . '/' . $action . $queryString;
    }

    /**
     * Get the first child for a given parent
     *
     * @param int $pageId The pageID wherefore we should retrieve the first child.
     *
     * @return int
     */
    public static function getFirstChildId($pageId)
    {
        $pageId = (int) $pageId;

        // init var
        $navigation = self::getNavigation();

        // loop depths
        foreach ($navigation as $parent) {
            // no available, skip this element
            if (!isset($parent[$pageId])) {
                continue;
            }

            // get keys
            $keys = array_keys($parent[$pageId]);

            // get first item
            if (isset($keys[0])) {
                return $keys[0];
            }
        }

        // fallback
        return false;
    }

    /**
     * Get all footer links
     *
     * @return array
     */
    public static function getFooterLinks()
    {
        // get the navigation
        $navigation = self::getNavigation();

        // init var
        $return = array();

        // validate
        if (!isset($navigation['footer'][0])) {
            return $return;
        }

        // loop links
        foreach ($navigation['footer'][0] as $id => $data) {
            // skip hidden pages
            if ($data['hidden']) {
                continue;
            }

            // build temp array
            $temp = array();
            $temp['id'] = $id;
            $temp['url'] = self::getURL($id);
            $temp['title'] = $data['title'];
            $temp['navigation_title'] = $data['navigation_title'];
            $temp['selected'] = (bool) in_array($id, self::$selectedPageIds);

            // add
            $return[] = $temp;
        }

        // return footer links
        return $return;
    }

    /**
     * Get the page-keys
     *
     * @param string $language The language wherefore the navigation should be loaded,
     *                         if not provided we will load the language that was provided in the URL.
     *
     * @return array
     */
    public static function getKeys($language = null)
    {
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        return BackendPagesModel::getCacheBuilder()->getKeys($language);
    }

    /**
     * Get the navigation-items
     *
     * @param string $language The language wherefore the keys should be loaded,
     *                         if not provided we will load the language that was provided in the URL.
     *
     * @return array
     */
    public static function getNavigation($language = null)
    {
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        return BackendPagesModel::getCacheBuilder()->getNavigation($language);
    }

    /**
     * Get navigation HTML
     *
     * @param string $type         The type of navigation the HTML should be build for.
     * @param int    $parentId     The parentID to start of.
     * @param int    $depth        The maximum depth to parse.
     * @param array  $excludeIds   PageIDs to be excluded.
     * @param string $template     The template that will be used.
     * @param int    $depthCounter A counter that will hold the current depth.
     *
     * @return string
     * @throws Exception
     */
    public static function getNavigationHTML(
        $type = 'page',
        $parentId = 0,
        $depth = null,
        $excludeIds = array(),
        $template = '/Core/Layout/Templates/Navigation.html.twig',
        $depthCounter = 1
    ) {
        // get navigation
        $navigation = self::getNavigation();

        // merge the exclude ids with the previously set exclude ids
        $excludeIds = array_merge((array) $excludeIds, self::$excludedPageIds);

        // meta-navigation is requested but meta isn't enabled
        if ($type == 'meta' &&
            (!Model::get('fork.settings')->get('Pages', 'meta_navigation', true) ||
             !isset($navigation['meta']))
        ) {
            return '';
        }

        // validate
        if (!isset($navigation[$type])) {
            throw new Exception(
                'This type (' . $type . ') isn\'t a valid navigation type. Possible values are: page, footer, meta.'
            );
        }
        if (!isset($navigation[$type][$parentId])) {
            throw new Exception('The parent (' . $parentId . ') doesn\'t exists.');
        }

        // special construction to merge home with its immediate children
        $mergedHome = false;
        while (true) {
            // loop elements
            foreach ($navigation[$type][$parentId] as $id => $page) {
                // home is a special item, it should live on the same depth
                if ($page['page_id'] == 1 && !$mergedHome) {
                    // extra checks otherwise exceptions will wbe triggered.
                    if (!isset($navigation[$type][$parentId]) ||
                        !is_array($navigation[$type][$parentId])
                    ) {
                        $navigation[$type][$parentId] = array();
                    }
                    if (!isset($navigation[$type][$page['page_id']]) ||
                        !is_array($navigation[$type][$page['page_id']])
                    ) {
                        $navigation[$type][$page['page_id']] = array();
                    }

                    // add children
                    $navigation[$type][$parentId] = array_merge(
                        $navigation[$type][$parentId],
                        $navigation[$type][$page['page_id']]
                    );

                    // mark as merged
                    $mergedHome = true;

                    // restart loop
                    continue 2;
                }

                // not hidden and not an action
                if ($page['hidden'] || $page['tree_type'] == 'direct_action') {
                    unset($navigation[$type][$parentId][$id]);
                    continue;
                }

                // authentication
                if (isset($page['data'])) {
                    // unserialize data
                    $page['data'] = unserialize($page['data']);
                    // if auth_required isset and is true
                    if (isset($page['data']['auth_required']) && $page['data']['auth_required']) {
                        // is profile logged? unset
                        if (!FrontendAuthentication::isLoggedIn()) {
                            unset($navigation[$type][$parentId][$id]);
                            continue;
                        }
                        // check if group auth is set
                        if (!empty($page['data']['auth_groups'])) {
                            $inGroup = false;
                            // loop group and set value true if one is found
                            foreach ($page['data']['auth_groups'] as $group) {
                                if (FrontendAuthentication::getProfile()->isInGroup($group)) {
                                    $inGroup = true;
                                }
                            }
                            // unset page if not in any of the groups
                            if (!$inGroup) {
                                unset($navigation[$type][$parentId][$id]);
                            }
                        }
                    }
                }

                // some ids should be excluded
                if (in_array($page['page_id'], (array) $excludeIds)) {
                    unset($navigation[$type][$parentId][$id]);
                    continue;
                }

                // if the item is in the selected page it should get an selected class
                if (in_array(
                    $page['page_id'],
                    self::$selectedPageIds
                )
                ) {
                    $navigation[$type][$parentId][$id]['selected'] = true;
                } else {
                    $navigation[$type][$parentId][$id]['selected'] = false;
                }

                // add nofollow attribute if needed
                if ($page['no_follow']) {
                    $navigation[$type][$parentId][$id]['nofollow'] = true;
                } else {
                    $navigation[$type][$parentId][$id]['nofollow'] = false;
                }

                // meta and footer subpages have the "page" type
                if ($type == 'meta' || $type == 'footer') {
                    $subType = 'page';
                } else {
                    $subType = $type;
                }

                // fetch children if needed
                if (isset($navigation[$subType][$page['page_id']]) && $page['page_id'] != 1 &&
                    ($depth == null || $depthCounter + 1 <= $depth)
                ) {
                    $navigation[$type][$parentId][$id]['children'] = self::getNavigationHTML(
                        $subType,
                        $page['page_id'],
                        $depth,
                        $excludeIds,
                        $template,
                        $depthCounter + 1
                    );
                } else {
                    $navigation[$type][$parentId][$id]['children'] = false;
                }

                // add parent id
                $navigation[$type][$parentId][$id]['parent_id'] = $parentId;

                // add depth
                $navigation[$type][$parentId][$id]['depth'] = $depthCounter;

                // set link
                $navigation[$type][$parentId][$id]['link'] = static::getURL($page['page_id']);

                // is this an internal redirect?
                if (isset($page['redirect_page_id']) && $page['redirect_page_id'] != '') {
                    $navigation[$type][$parentId][$id]['link'] = static::getURL(
                        (int) $page['redirect_page_id']
                    );
                }

                // is this an external redirect?
                if (isset($page['redirect_url']) && $page['redirect_url'] != '') {
                    $navigation[$type][$parentId][$id]['link'] = $page['redirect_url'];
                }
            }

            // break the loop (it is only used for the special construction with home)
            break;
        }

        // return parsed content
        return Model::get('templating')->render(
            $template,
            array('navigation' => $navigation[$type][$parentId])
        );
    }

    /**
     * Get a menuId for an specified URL
     *
     * @param string $url      The URL wherefore you want a pageID.
     * @param string $language The language wherefore the pageID should be retrieved,
     *                          if not provided we will load the language that was provided in the URL.
     *
     * @return int
     */
    public static function getPageId($url, $language = null)
    {
        // redefine
        $url = trim((string) $url, '/');
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        // get menu items array
        $keys = self::getKeys($language);

        // get key
        $key = array_search($url, $keys);

        // return 404 if we don't known a valid Id
        if ($key === false) {
            return 404;
        }

        // return the real Id
        return (int) $key;
    }

    /**
     * Get more info about a page
     *
     * @param int $pageId The pageID wherefore you want more information.
     *
     * @return string
     */
    public static function getPageInfo($pageId)
    {
        // get navigation
        $navigation = self::getNavigation();

        // loop levels
        foreach ($navigation as $level) {
            // loop parents
            foreach ($level as $parentId => $children) {
                // loop children
                foreach ($children as $itemId => $item) {
                    // return if this is the requested item
                    if ($pageId == $itemId) {
                        // set return
                        $return = $item;
                        $return['page_id'] = $itemId;
                        $return['parent_id'] = $parentId;

                        // return
                        return $return;
                    }
                }
            }
        }

        // fallback
        return false;
    }

    /**
     * Get URL for a given pageId
     *
     * @param int    $pageId   The pageID wherefore you want the URL.
     * @param string $language The language wherein the URL should be retrieved,
     *                         if not provided we will load the language that was provided in the URL.
     *
     * @return string
     */
    public static function getURL($pageId, $language = null)
    {
        $pageId = (int) $pageId;
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        // init URL
        $url = (FrontendModel::getContainer()->getParameter('site.multilanguage'))
            ? '/' . $language . '/'
            : '/'
        ;

        // get the menuItems
        $keys = self::getKeys($language);

        // get the URL, if it doesn't exist return 404
        if (!isset($keys[$pageId])) {
            return self::getURL(404, $language);
        } else {
            $url .= $keys[$pageId];
        }

        // return the URL
        return urldecode($url);
    }

    /**
     * Get the URL for a give module & action combination
     *
     * @param string $module   The module wherefore the URL should be build.
     * @param string $action   The specific action wherefore the URL should be build.
     * @param string $language The language wherein the URL should be retrieved,
     *                         if not provided we will load the language that was provided in the URL.
     * @param array $data      An array with keys and values that partially or fully match the data of the block.
     *                         If it matches multiple versions of that block it will just return the first match.
     *
     * @return string
     */
    public static function getURLForBlock($module, $action = null, $language = null, array $data = null)
    {
        $module = (string) $module;
        $action = ($action !== null) ? (string) $action : null;
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        // init var
        $pageIdForURL = null;

        // get the menuItems
        $navigation = self::getNavigation($language);

        $dataMatch = false;
        // loop types
        foreach ($navigation as $level) {
            // loop level
            foreach ($level as $pages) {
                // loop pages
                foreach ($pages as $pageId => $properties) {
                    // only process pages with extra_blocks that are visible
                    if (!isset($properties['extra_blocks']) || $properties['hidden']) {
                        continue;
                    }

                    // loop extras
                    foreach ($properties['extra_blocks'] as $extra) {
                        // direct link?
                        if ($extra['module'] == $module && $extra['action'] == $action  && $extra['action'] !== null) {
                            // if there is data check if all the requested data matches the extra data
                            if (isset($extra['data']) && $data !== null
                                && array_intersect_assoc($data, (array) $extra['data']) !== $data) {
                                // It is the correct action but has the wrong data
                                continue;
                            }
                            // exact page was found, so return
                            return self::getURL($properties['page_id'], $language);
                        }

                        if ($extra['module'] == $module && $extra['action'] == null) {
                            // if there is data check if all the requested data matches the extra data
                            if (isset($extra['data']) && $data !== null) {
                                if (array_intersect_assoc($data, (array) $extra['data']) !== $data) {
                                    // It is the correct module but has the wrong data
                                    continue;
                                }

                                $pageIdForURL = (int) $pageId;
                                $dataMatch = true;
                            }

                            if ($extra['data'] === null && $data === null) {
                                $pageIdForURL = (int) $pageId;
                                $dataMatch = true;
                            }

                            if (!$dataMatch) {
                                $pageIdForURL = (int) $pageId;
                            }
                        }
                    }
                }
            }
        }

        // pageId still null?
        if ($pageIdForURL === null) {
            return self::getURL(404, $language);
        }

        // build URL
        $url = self::getURL($pageIdForURL, $language);

        // append action
        if ($action !== null) {
            $url .= '/' . Language::act(\SpoonFilter::toCamelCase($action));
        }

        // return the URL
        return $url;
    }

    /**
     * Fetch the first direct link to an extra id
     *
     * @param int    $id       The id of the extra.
     * @param string $language The language wherein the URL should be retrieved,
     *                         if not provided we will load the language that was provided in the URL.
     *
     * @return string
     */
    public static function getURLForExtraId($id, $language = null)
    {
        $id = (int) $id;
        $language = ($language !== null) ? (string) $language : LANGUAGE;

        // get the menuItems
        $navigation = self::getNavigation($language);

        // loop types
        foreach ($navigation as $level) {
            // loop level
            foreach ($level as $pages) {
                // loop pages
                foreach ($pages as $properties) {
                    // no extra_blocks available, so skip this item
                    if (!isset($properties['extra_blocks'])) {
                        continue;
                    }

                    // loop extras
                    foreach ($properties['extra_blocks'] as $extra) {
                        // direct link?
                        if ($extra['id'] == $id) {
                            // exact page was found, so return
                            return self::getURL($properties['page_id'], $language);
                        }
                    }
                }
            }
        }

        // fallback
        return self::getURL(404, $language);
    }

    /**
     * This function lets you add ignored pages
     *
     * @param mixed $pageIds This can be a single page id or this can be an array with page ids.
     */
    public static function setExcludedPageIds($pageIds)
    {
        $pageIds = (array) $pageIds;

        // go trough the page ids to add them to the excluded page ids for later usage
        foreach ($pageIds as $pageId) {
            array_push(self::$excludedPageIds, $pageId);
        }
    }

    /**
     * Set the selected page ids
     */
    public function setSelectedPageIds()
    {
        // get pages
        $pages = (array) $this->URL->getPages();

        // no pages, means we're at the homepage
        if (empty($pages)) {
            self::$selectedPageIds[] = 1;
        } else {
            // loop pages
            while (!empty($pages)) {
                // get page id
                $pageId = self::getPageId((string) implode('/', $pages));

                // add pageId into selected items
                if ($pageId !== false) {
                    self::$selectedPageIds[] = $pageId;
                }

                // remove last element
                array_pop($pages);
            }
        }
    }
}

<?php

/**
 * @file IndexController.php
 * @brief This file contains the IndexController class.
 * @details
 * @author Filippo F. Fadda
 */


namespace ReIndex\Controller;


use EoC\Couch;
use EoC\Opt\ViewQueryOpts;

use ToolBag\Helper;

use ReIndex\Doc\Post;


/**
 * @brief Controller of Index actions.
 * @nosubgrouping
 */
class IndexController extends ListController {

  // Actions that aren't listing actions.
  protected static $actions = ['show', 'edit', 'new'];

  // The controller name and also the document's type.
  protected $type;

  // Periods of time.
  protected $periods;


  /**
   * @brief Returns a human readable label for the controller.
   * @retval string
   */
  protected function getLabel() {
    return 'posts';
  }


  /**
   * @brief Returns `true` if the caller object is an instance of the class implementing this method, `false` otherwise.
   * @retval bool
   */
  protected function isSameClass() {
    return get_class($this) == get_class();
  }


  /**
   * @brief Given a tag's name, returns its id.
   * @param[in] string $name The tag's name.
   * @retval string|bool Returns the tag id, or `false` in case the tag doesn't exist.
   */
  protected function getTagId($name) {
    $opts = new ViewQueryOpts();
    $opts->setLimit(1)->setKey($name);

    // tags/byName/view
    $rows = $this->couch->queryView('tags', 'byName', 'view', NULL, $opts);

    if ($rows->isEmpty())
      return FALSE;
    else
      return current($rows->getIterator())['id'];
  }


  /*
   * @brief Retrieves information for a bunch of posts.
   * @param[in] string $designDocName The name of the design document.
   * @param[in] string $type The type of posts.
   * @param[in] int $count The number of requested posts.
   */
  protected function getInfo($designDocName, $type, $count = 10) {
    $opts = new ViewQueryOpts();
    $opts->doNotReduce()->setLimit($count)->reverseOrderOfResults()->setStartKey([$type, Couch::WildCard()])->setEndKey([$type]);
    $rows = $this->couch->queryView('posts', $designDocName, 'view', NULL, $opts);

    if ($rows->isEmpty())
      return NULL;

    // Entries.
    $ids = array_column($rows->asArray(), 'id');

    // Posts.
    $opts->reset();
    $opts->doNotReduce()->includeMissingKeys();
    // posts/info/view
    $posts = $this->couch->queryView('posts', 'info', 'view', $ids, $opts);

    Helper\ArrayHelper::unversion($ids);

    // Scores.
    $opts->reset();
    $opts->includeMissingKeys()->groupResults();
    // votes/perItem/view
    $scores = $this->couch->queryView('votes', 'perItem', 'view', $ids, $opts);

    // Comments.
    $opts->reset();
    $opts->includeMissingKeys()->groupResults();
    // comments/perItem/view
    $comments = $this->couch->queryView('comments', 'perItem', 'view', $ids, $opts);

    $entries = [];
    $postCount = count($posts);
    for ($i = 0; $i < $postCount; $i++) {
      $entry = new \stdClass();
      $entry->id = $posts[$i]['id'];

      $properties = $posts[$i]['value'];
      $entry->title = $properties['title'];
      $entry->url = Helper\TextHelper::buildUrl($properties['publishedAt'], $properties['slug']);
      $entry->whenHasBeenPublished = Helper\TimeHelper::when($properties['publishedAt']);
      $entry->score = is_null($scores[$i]['value']) ? 0 : $scores[$i]['value'];
      $entry->commentsCount = is_null($comments[$i]['value']) ? 0 : $comments[$i]['value'];

      $entries[] = $entry;
    }

    return $entries;
  }


  /**
   * @brief Gets a list of tags recently updated.
   * @param[in] int $count The number of tags to be returned.
   */
  protected function recentTags($count = 20) {
    $recentTags = [];

    if ($this->isSameClass()) {
      $act = Post::ACT_TAGS_SET . 'post';
      $pop = Post::POP_TAGS_SET . 'post';
    }
    else {
      $act = Post::ACT_TAGS_SET . $this->type;
      $pop = Post::POP_TAGS_SET . $this->type;
    }

    $ids = $this->redis->zRevRangeByScore($act, '+inf', 0, ['limit' => [0, $count]]);

    if (!empty($ids)) {
      // tags/names/view
      $names = $this->couch->queryView('tags', 'names', 'view', $ids);

      $count = count($ids);
      for ($i = 0; $i < $count; $i++)
        $recentTags[] = [$names[$i]['value'], $this->redis->zScore($pop, $ids[$i])];
    }

    $this->view->setVar('recentTags', $recentTags);
  }


  /**
   * @brief Adds CodeMirror Editor files.
   */
  protected function addCodeMirror() {
    $codeMirrorPath = "//cdnjs.cloudflare.com/ajax/libs/codemirror/".$this->di['config']['assets']['codeMirrorVersion'];
    $this->assets->addCss($codeMirrorPath."/codemirror.min.css", FALSE);
    $this->assets->addJs($codeMirrorPath."/codemirror.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/addon/mode/overlay.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/xml/xml.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/markdown/markdown.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/gfm/gfm.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/javascript/javascript.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/css/css.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/htmlmixed/htmlmixed.min.js", FALSE);
    $this->assets->addJs($codeMirrorPath."/mode/clike/clike.min.js", FALSE);
  }


  /**
   * @brief Returns `true` when the called action is a listing action.
   * @retval bool
   */
  protected function isListing() {
    if (!in_array($this->actionName, static::$actions))
      return TRUE;
    else
      return FALSE;
  }


  /**
   * @brief Retrieves all post IDs in a set between min and max.
   * @param[in] string $prefix Prefix of the Redis set.
   * @param[in] string $postfix Postfix of the Redis set.
   * @param[in] string $unversionTagId (optional) An optional unversioned tag ID.
   * @param[in] mixed $min Minimum score.
   * @param[in] mixed $max Maximum score.
   */
  protected function zRevRangeByScore($prefix, $postfix = '', $unversionTagId = NULL, $min = 0, $max = '+inf') {
    $subset = is_null($unversionTagId) ? '' : $unversionTagId . '_';

    if ($this->isSameClass())
      $set = $prefix . $subset . "post" . $postfix;
    else
      $set = $prefix . $subset . $this->type . $postfix;

    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $keys = $this->redis->zRevRangeByScore($set, $max, $min, ['limit' => [$offset, (int)$this->resultsPerPage]]);
    $count = $this->redis->zCount($set, $min, $max);

    $nextOffset = $offset + $this->resultsPerPage;

    if ($count > $nextOffset)
      $this->view->setVar('nextPage', $this->buildPaginationUrlForRedis($nextOffset));

    if (!empty($keys)) {
      $opts = new ViewQueryOpts();
      $opts->doNotReduce();
      // posts/byUnversionId/view
      $rows = $this->couch->queryView('posts', 'byUnversionId', 'view', $keys, $opts);
      $posts = Post::collect(array_column($rows->asArray(), 'id'));
    }
    else
      $posts = [];

    $this->view->setVar('entries', $posts);
    $this->view->setVar('entriesCount', Helper\TextHelper::formatNumber($count));
  }


  /**
   * @brief Used by perDateAction() and perDateByTagAction().
   * @param[in] \DateTime $minDate Minimum date.
   * @param[in] \DateTime $maxDate Maximum date.
   * @param[in] string $unversionTagId (optional) An optional unversioned tag ID.
   */
  protected function perDate(\DateTime $minDate, \DateTime $maxDate, $unversionTagId = NULL) {
    $this->zRevRangeByScore(Post::NEW_SET, '', $unversionTagId, $minDate->getTimestamp(), $maxDate->getTimestamp());

    $this->view->setVar('title', sprintf('%s by date', ucfirst($this->getLabel())));
  }


  /**
   * @brief Used by newestAction() and newestByTagAction().
   * @param[in] string $unversionTagId (optional) An optional unversioned tag ID.
   */
  protected function newest($unversionTagId = NULL) {
    $this->zRevRangeByScore(Post::NEW_SET, '', $unversionTagId);

    if (is_null($this->view->title))
      $this->view->setVar('title', sprintf('New %s', $this->getLabel()));
  }


  /**
   * @brief Used by popularAction() and popularByTagAction().
   * @param[in] string $filter A human readable period of time.
   * @param[in] string $unversionTagId (optional) An optional unversioned tag ID.
   */
  protected function popular($filter, $unversionTagId = NULL) {
    $period = Helper\TimeHelper::period($filter);
    if ($period === FALSE) return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $this->dispatcher->setParam('period', $period);

    $postfix = Helper\TimeHelper::aWhileBack($period, "_");

    $this->zRevRangeByScore(Post::POP_SET, $postfix, $unversionTagId);

    $this->view->setVar('periods', $this->periods);
    $this->view->setVar('title', sprintf('Popular %s', ucfirst($this->getLabel())));
  }


  /**
   * @brief Used by activeAction() and activeByTagAction().
   * @param[in] string $unversionTagId (optional) An optional unversioned tag ID.
   */
  protected function active($unversionTagId = NULL) {
    $this->zRevRangeByScore(Post::ACT_SET, '', $unversionTagId);

    $this->view->setVar('title', sprintf('Active %s', ucfirst($this->getLabel())));
  }


  public function initialize() {
    // Prevents to call the method twice in case of forwarding.
    if ($this->dispatcher->isFinished() && $this->dispatcher->wasForwarded())
      return;

    parent::initialize();

    if ($this->isListing()) {
      $this->type = $this->controllerName;
      $this->resultsPerPage = $this->di['config']->application->postsPerPage;
      $this->periods = Helper\TimeHelper::$periods;

      $this->assets->addJs($this->dist."/js/tab.min.js", FALSE);
      $this->assets->addJs($this->dist."/js/list.min.js", FALSE);

      // FOR DEBUG PURPOSE ONLY UNCOMMENT THE FOLLOWING LINE AND COMMENT THE ONE ABOVE.
      //$this->assets->addJs("/reindex/themes/".$this->themeName."/src/js/list.js", FALSE);

      $this->view->pick('views/index');
    }
  }


  public function afterExecuteRoute() {
    // Prevents to call the method twice in case of forwarding.
    if ($this->dispatcher->isFinished() && $this->dispatcher->wasForwarded())
      return;

    parent::afterExecuteRoute();

    if ($this->isListing()) {
      $this->recentTags();

      // The entries label is printed below the entries count.
      $this->view->setVar('entriesLabel', $this->getLabel());

      // Those are the notebook pages, printed using the `updates.volt` widget.
      //$this->view->setVar('questions', $this->getInfo('perDateByType', 'question'));
      //$this->view->setVar('articles', $this->getInfo('perDateByType', 'article'));
      //$this->view->setVar('books', $this->getInfo('perDateByType', 'book'));

      $this->log->addDebug(sprintf('Type: %s', $this->type));
    }

  }


  /**
   * @brief Page index.
   */
  public function indexAction() {
    if ($this->user->isMember()) {
      $this->view->setVar('title', 'Home');
      $this->actionName = 'newest';

      return $this->dispatcher->forward(
        [
          'controller' => 'index',
          'action' => 'newest'
        ]);
    }
    else
      return $this->dispatcher->forward(
        [
          'controller' => 'auth',
          'action' => 'logon'
        ]);
  }


  /**
   * @brief Page index by tag.
   * @param[in] string $tag The tag name.
   */
  public function indexByTagAction($tag) {
    $this->actionName = 'newestByTag';

    return $this->dispatcher->forward(
      [
        'controller' => 'index',
        'action' => 'newestByTag',
        'params' => [$tag]
      ]);
  }


  /**
   * @brief Displays information about the tag.
   * @param[in] string $tag The tag name.
   */
  public function infoByTagAction($tag) {
    $this->view->setVar('title', 'Popular tags');
  }


  /**
   * @brief Displays the posts per date.
   * @param[in] int $year An year.
   * @param[in] int $month (optional) A month.
   * @param[in] int $day (optional) A specific day.
   */
  public function perDateAction($year, $month = NULL, $day = NULL) {
    Helper\TimeHelper::dateLimits($minDate, $maxDate, $year, $month, $day);

    $this->perDate($minDate, $maxDate);
  }


  /**
   * @brief Displays the posts per date by tag.
   * @param[in] string $tag The tag name.
   * @param[in] int $year An year.
   * @param[in] int $month (optional) A month.
   * @param[in] int $day (optional) A specific day.
   */
  public function perDateByTagAction($tag, $year, $month = NULL, $day = NULL) {
    $tagId = $this->getTagId($tag);
    if ($tagId === FALSE) return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    Helper\TimeHelper::dateLimits($minDate, $maxDate, $year, $month, $day);

    $this->perDate($minDate, $maxDate, Helper\TextHelper::unversion($tagId));

    $this->view->setVar('etag', $this->couch->getDoc('tags', Couch::STD_DOC_PATH, $tagId));
  }


  /**
   * @brief Displays the newest posts.
   */
  public function newestAction() {
    $this->newest();
  }


  /**
   * @brief Displays the newest posts by tag.
   * @param[in] string $tag The tag name.
   */
  public function newestByTagAction($tag) {
    $tagId = $this->getTagId($tag);
    if ($tagId === FALSE) return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $this->newest(Helper\TextHelper::unversion($tagId));

    $this->view->setVar('etag', $this->couch->getDoc('tags', Couch::STD_DOC_PATH, $tagId));
  }


  /**
   * @brief Displays the most popular updates for the provided period (ordered by score).
   * @param[in] string $filter (optional) Human readable representation of a period.
   */
  public function popularAction($filter = NULL) {
    $this->popular($filter);
  }


  /**
   * @brief Displays the most popular updates by tag, for the provided period (ordered by score).
   * @param[in] string $tag The tag name.
   * @param[in] string $filter (optional) Human readable representation of a period.
   */
  public function popularByTagAction($tag, $filter = NULL) {
    $tagId = $this->getTagId($tag);
    if ($tagId === FALSE) return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $this->popular($filter, Helper\TextHelper::unversion($tagId));

    $this->view->setVar('etag', $this->couch->getDoc('tags', Couch::STD_DOC_PATH, $tagId));
  }


  /**
   * @brief Displays the last updated entries.
   */
  public function activeAction() {
    $this->active();
  }


  /**
   * @brief Displays the last updated entries by tag.
   * @param[in] string $tag The tag name.
   */
  public function activeByTagAction($tag) {
    $tagId = $this->getTagId($tag);
    if ($tagId === FALSE) return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $this->active(Helper\TextHelper::unversion($tagId));

    $this->view->setVar('etag', $this->couch->getDoc('tags', Couch::STD_DOC_PATH, $tagId));
  }


  /**
   * @brief Displays the newest updates based on my tags.
   */
  public function interestingAction() {
    $this->view->setVar('title', sprintf('%s associated with your favorite tags', ucfirst($this->getLabel())));
  }


  /**
   * @brief Displays the post.
   * @todo Before to send a 404, we have check if does a post exist for the provided url, because maybe it's an old
   * revision of the same posts. Use the posts/approvedRevisionsByUrl view to check the existence, then make another
   * query on the posts/unversion to get the postId, and finally use it to get the document.
   * @param[in] int $year The year when a post has been published.
   * @param[in] int $month The month when a post has been published.
   * @param[in] int $day The exact day when a post has been published.
   * @param[in] string $slug The post' slug.
   */
  public function displayBySlugAction($year, $month, $day, $slug) {
    $opts = new ViewQueryOpts();
    $opts->setKey([$year, $month, $day, $slug])->setLimit(1);
    // posts/byUrl/view
    $rows = $this->couch->queryView('posts', 'byUrl', 'view', NULL, $opts);

    if ($rows->isEmpty())
      return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $post = $this->couch->getDoc('posts', Couch::STD_DOC_PATH, $rows[0]['id']);
    
    //$this->assets->addJs($this->dist."/js/post.min.js", FALSE);
    // FOR DEBUG PURPOSE ONLY UNCOMMENT THE FOLLOWING LINE AND COMMENT THE ONE ABOVE.
    $this->assets->addJs("/reindex/themes/".$this->themeName."/src/js/post.js", FALSE);

    $post->viewAction($this);
  }


  /**
   * @brief Edits the post.
   * @param[in] string $id The post ID.
   */
  public function editAction($id = NULL) {
    if (empty($id))
      return $this->dispatcher->forward(['controller' => 'error', 'action' => 'show404']);

    $post = $this->couchdb->getDoc('posts', Couch::STD_DOC_PATH, $id);

    $this->addCodeMirror();
    $this->assets->addJs($this->dist."/js/selectize.min.js", FALSE);

    $post->editAction($this);
  }

}
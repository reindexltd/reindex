<?php

/**
 * @file ListController.php
 * @brief This file contains the ListController class.
 * @details
 * @author Filippo F. Fadda
 */


namespace PitPress\Controller;


use ElephantOnCouch\Opt\ViewQueryOpts;

use PitPress\Helper;
use PitPress\Model\User\User;

use Phalcon\Mvc\View;


/*
 * @brief Ancestor controller for any controller displaying posts.
 * @nosubgrouping
 */
abstract class ListController extends BaseController {


  /**
   * @brief Given a set of keys, retrieves entries.
   */
  protected function getEntries($keys) {
    if (empty($keys))
      return [];

    $opts = new ViewQueryOpts();

    // Posts.
    $opts->doNotReduce();
    $posts = $this->couch->queryView("posts", "all", $keys, $opts);

    // Likes.
    if (isset($this->user)) {
      $opts->reset();
      $opts->doNotReduce()->includeMissingKeys();

      $complex = [];
      foreach ($keys as $postId)
        $complex[] = [$postId, $this->user->id];

      $likes = $this->couch->queryView("votes", "perPostAndUser", $complex, $opts);
    }

    // Scores.
    $opts->reset();
    $opts->includeMissingKeys()->groupResults();
    $scores = $this->couch->queryView("votes", "perPost", $keys, $opts);

    // Replies.
    $opts->reset();
    $opts->includeMissingKeys()->groupResults();
    $replies = $this->couch->queryView("replies", "perPost", $keys, $opts);

    // Users.
    $keys = array_column(array_column($posts->asArray(), 'value'), 'userId');
    $opts->reset();
    $opts->doNotReduce()->includeMissingKeys();
    $users = $this->couch->queryView("users", "allNames", $keys, $opts);

    $entries = [];
    $postCount = count($posts);
    for ($i = 0; $i < $postCount; $i++) {
      $entry = (object)($posts[$i]['value']);
      $entry->id = $posts[$i]['id'];
      $entry->url = $this->buildUrl($entry->publishingDate, $entry->slug);
      $entry->whenHasBeenPublished = Helper\Time::when($entry->publishingDate);
      $entry->username = $users[$i]['value'][0];
      $entry->gravatar = User::getGravatar($users[$i]['value'][1]);
      $entry->hitsCount = $this->redis->hGet($entry->id, 'hits');
      $entry->score = is_null($scores[$i]['value']) ? 0 : $scores[$i]['value'];
      $entry->repliesCount = is_null($replies[$i]['value']) ? 0 : $replies[$i]['value'];
      $entry->liked = is_null($this->user) || is_null($likes[$i]['value']) ? FALSE : TRUE;

      // Tags.
      $opts->reset();
      $opts->doNotReduce()->setKey($entry->id);
      $classifications = $this->couch->queryView("classifications", "perPost", NULL, $opts);

      if (!$classifications->isEmpty()) {
        $keys = array_column($classifications->asArray(), 'value');
        $opts->reset();
        $opts->doNotReduce();
        $entry->tags = $this->couch->queryView("tags", "allNames", $keys, $opts);
      }
      else
        $entry->tags = [];

      $entries[] = $entry;
    }

    return $entries;
  }


  /**
   * @brief Builds the post url, given its publishing date and slug.
   * @return string The complete url of the post.
   */
  protected function buildUrl($publishingDate, $slug) {
    return "http://".$this->domainName.date('/Y/m/d/', $publishingDate).$slug;
  }


  public function initialize() {
    parent::initialize();

    $this->assets->addJs("/pit-bootstrap/dist/js/list.min.js", FALSE);
  }


  public function afterExecuteRoute() {
    parent::afterExecuteRoute();
  }

}
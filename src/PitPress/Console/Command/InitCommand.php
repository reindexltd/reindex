<?php

//! @file InitCommand.php
//! @brief This file contains the InitCommand class.
//! @details
//! @author Filippo F. Fadda


namespace PitPress\Console\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use ElephantOnCouch\Doc\DesignDoc;
use ElephantOnCouch\Handler\ViewHandler;


//! @brief Initializes the PitPress database, adding the required design documents.
//! @nosubgrouping
class InitCommand extends AbstractCommand {

  protected $mysql;
  protected $couch;


  //! @brief Insert all design documents.
  private function initAll() {
    $this->initPosts();
    $this->initTags();
    $this->initVotes();
    $this->initScores();
    $this->initStars();
    $this->initSubscriptions();
    $this->initClassifications();
    $this->initReputation();
    $this->initBadges();
    $this->initFavorites();
    $this->initUsers();
    $this->initReplies();
  }


  private function initDocs() {
    $doc = DesignDoc::create('docs');

    function docsByType() {
      $map = "function(\$doc) use (\$emit) {
                \$emit(\$doc->type);
              };";

      $handler = new ViewHandler("byType");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(docsByType());


    $this->couch->saveDoc($doc);
  }


  private function initPosts() {
    $doc = DesignDoc::create('posts');


    // @params: NONE
    function allPosts() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit(\$doc->_id, [
                     'type' => \$doc->type,
                     'title' => \$doc->title,
                     'excerpt' => \$doc->excerpt,
                     'slug' => \$doc->slug,
                     'section' => \$doc->section,
                     'publishingType' => \$doc->publishingType,
                     'publishingDate' => \$doc->publishingDate,
                     'userId' => \$doc->userId,
                     'username' => \$doc->username
                   ]);
              };";

      $handler = new ViewHandler("all");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(allPosts());


    // @params: section, year, month, day, slug
    function postsByUrl() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit([\$doc->section, \$doc->year, \$doc->month, \$doc->day, \$doc->slug]);
              };";

      $handler = new ViewHandler("byUrl");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(postsByUrl());


    // @params: userId
    function newestPostsByUser() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit([\$doc->userId, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("newestByUser");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(newestPostsByUser());


    // @params: userId, type
    function newestPostsByUserPerType() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit([\$doc->userId, \$doc->type, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("newestByUserPerType");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(newestPostsByUserPerType());


    // @params: type
    function newestPostsPerType() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit([\$doc->type, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("newestPerType");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(newestPostsPerType());


    // @params: section
    function newestPostsPerSection() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->section))
                  \$emit([\$doc->section, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("newestPerSection");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(newestPostsPerSection());


    // @params: NONE
    function newestPosts() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit(\$doc->publishingDate);
              };";

      $handler = new ViewHandler("newest");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount(); // Used to count the posts.

      return $handler;
    }

    $doc->addHandler(newestPosts());


    // @params: NONE
    function postsPerDate() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'post')
                  \$emit([\$doc->year, \$doc->month, \$doc->day]);
              };";

      $handler = new ViewHandler("perDate");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(postsPerDate());


    $this->couch->saveDoc($doc);
  }


  private function initTags() {
    $doc = DesignDoc::create('tags');


    function allTags() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'tag')
                  \$emit(\$doc->_id, [\$doc->name, \$doc->excerpt, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("all");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(allTags());


    function allNames() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'tag')
                  \$emit(\$doc->_id, \$doc->name);
              };";

      $handler = new ViewHandler("allNames");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(allNames());


    function newestTags() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'tag')
                  \$emit(\$doc->publishingDate);
              };";

      $handler = new ViewHandler("newest");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(newestTags());


    function tagsByName() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'tag')
                  \$emit(\$doc->name);
              };";

      $handler = new ViewHandler("byName");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(tagsByName());


    $this->couch->saveDoc($doc);
  }


  private function initVotes() {
    $doc = DesignDoc::create('votes');


    // @params: [postId]
    function votesPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit(\$doc->postId, \$doc->value);
              };";

      $handler = new ViewHandler("perPost");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnSum(); // Used to count the votes.

      return $handler;
    }

    $doc->addHandler(votesPerPost());


    // @params: postId, userId
    function votesPerPostAndUser() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit([\$doc->postId, \$doc->userId], \$doc->value);
              };";

      $handler = new ViewHandler("perPostAndUser");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnSum(); // Used to count the votes.

      return $handler;
    }

    $doc->addHandler(votesPerPostAndUser());


    // @params: [userId]
    function votesPerUser() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit(\$doc->userId);
              };";

      $handler = new ViewHandler("perUser");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(votesPerUser());


    // @params: type, postId
    function votesPerType() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit([\$doc->postType, \$doc->postId], \$doc->value);
              };";

      $handler = new ViewHandler("perType");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnSum(); // Used to count the votes.

      return $handler;
    }

    $doc->addHandler(votesPerType());


    // @params: section, postId
    function votesPerSection() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit([\$doc->section, \$doc->postId], \$doc->value);
              };";

      $handler = new ViewHandler("perSection");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnSum(); // Used to count the votes.

      return $handler;
    }

    $doc->addHandler(votesPerSection());


    // @params: timestamp
    function votesNotRecorded() {
      $map = "function(\$doc) use (\$emit) {
                if ((\$doc->type == 'vote') && (!\$doc->recorded))
                  \$emit(\$doc->timestamp, \$doc);
              };";

      $handler = new ViewHandler("notRecorded");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(votesNotRecorded());


    $this->couch->saveDoc($doc);
  }


  private function initScores() {
    $doc = DesignDoc::create('scores');


    // @params postId
    function scoresPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'score')
                  \$emit(\$doc->postId, \$doc);
              };";

      $handler = new ViewHandler("perPost");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(scoresPerPost());


    // @params: type
    function scoresPerType() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'score')
                  \$emit([\$doc->postType, \$doc->points], \$doc->postId);
              };";

      $handler = new ViewHandler("perType");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(scoresPerType());


    // @params: section
    function scoresPerSection() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'score')
                  \$emit([\$doc->postSection, \$doc->points], \$doc->postId);
              };";

      $handler = new ViewHandler("perSection");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(scoresPerSection());


    $this->couch->saveDoc($doc);
  }


  private function initStars() {
    $doc = DesignDoc::create('stars');


    // @params postId, [userId]
    // @methods: VersionedItem.isStarred(), VersionedItem.getStarsCount()
    function starsPerItem() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'star')
                  \$emit([\$doc->itemId, \$doc->userId]);
              };";

      $handler = new ViewHandler("perItem");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(starsPerItem());


    $this->couch->saveDoc($doc);
  }


  private function initSubscriptions() {
    $doc = DesignDoc::create('subscriptions');


    // @params itemId, [userId]
    // @methods: VersionedItem.isStarred(), VersionedItem.getSubscribersCount()
    function subscriptionsPerItem() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'subscription')
                  \$emit([\$doc->itemId, \$doc->userId]);
              };";

      $handler = new ViewHandler("perItem");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(subscriptionsPerItem());


    $this->couch->saveDoc($doc);
  }


  private function initClassifications() {
    $doc = DesignDoc::create('classifications');


    // @params postId
    // @methods: Post.getTags()
    function classificationsPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'classification')
                  \$emit(\$doc->postId, \$doc->tagId);
              };";

      $handler = new ViewHandler("perPost");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(classificationsPerPost());


    // @params NONE
    function newestClassifications() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'classification')
                  \$emit(\$doc->timestamp, \$doc->tagId);
              };";

      $handler = new ViewHandler("newest");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(newestClassifications());


    // @params tagId
    function classificationsPerTag() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'classification')
                  \$emit(\$doc->tagId);
              };";

      $handler = new ViewHandler("perTag");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(classificationsPerTag());


    $this->couch->saveDoc($doc);
  }


  private function initBadges() {
  }


  private function initReputation() {
    $doc = DesignDoc::create('reputation');


    // @params userId, [timestamp]
    // @methods: User.getReputation()
    function reputationPerUser() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'reputation')
                  \$emit([\$doc->userId, \$doc->timestamp], \$doc->points);
              };";

      $handler = new ViewHandler("perUser");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnSum();

      return $handler;
    }

    $doc->addHandler(reputationPerUser());


    $this->couch->saveDoc($doc);
  }


  private function initFavorites() {
    $doc = DesignDoc::create('favorites');


    // @params userId
    // @methods: todo
    function lastAddedFavorites() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'star')
                  \$emit([\$doc->userId, \$doc->timestamp], \$doc->itemId);
              };";

      $handler = new ViewHandler("lastAdded");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(lastAddedFavorites());


    // @params userId, type
    // @methods: todo
    function lastAddedFavoritesPerType() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'star')
                  \$emit([\$doc->userId, \$doc->itemType, \$doc->timestamp], \$doc->itemId);
              };";

      $handler = new ViewHandler("lastAddedPerType");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(lastAddedFavoritesPerType());


    $this->couch->saveDoc($doc);
  }


  private function initUsers() {
    $doc = DesignDoc::create('users');


    // @params: [userId]
    function allUsers() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'user')
                  \$emit(\$doc->_id, [\$doc->displayName, \$doc->email, \$doc->creationDate]);
              };";

      $handler = new ViewHandler("all");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(allUsers());


    // @params: [userId]
    function allUserNames() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'user')
                  \$emit(\$doc->_id, [\$doc->displayName, \$doc->email]);
              };";

      $handler = new ViewHandler("allNames");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(allUserNames());


    function newestUsers() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'user')
                  \$emit(\$doc->creationDate);
              };";

      $handler = new ViewHandler("newest");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(newestUsers());


    function usersByDisplayName() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'user')
                  \$emit(\$doc->displayName);
              };";

      $handler = new ViewHandler("byDisplayName");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(usersByDisplayName());


    function usersByEmail() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'user')
                  \$emit(\$doc->email);
              };";

      $handler = new ViewHandler("byEmail");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(usersByEmail());


    // @params: [postId]
    function usersHaveVoted() {
      $map = "function(\$doc) use (\$emit) {
                if (\$doc->type == 'vote')
                  \$emit(\$doc->postId, \$doc->userId);
              };";

      $handler = new ViewHandler("haveVoted");
      $handler->mapFn = $map;

      return $handler;
    }

    $doc->addHandler(usersHaveVoted());


    $this->couch->saveDoc($doc);
  }


  private function initReplies() {
    $doc = DesignDoc::create('replies');


    // @params postId
    function repliesPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'reply')
                  \$emit(\$doc->postId);
              };";

      $handler = new ViewHandler("perPost");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(repliesPerPost());


    // @params: postId
    function newestRepliesPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'reply')
                  \$emit([\$doc->postId, \$doc->publishingDate]);
              };";

      $handler = new ViewHandler("newestPerPost");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(newestRepliesPerPost());


    // @params: postId
    function lastUpdatedRepliesPerPost() {
      $map = "function(\$doc) use (\$emit) {
                if (isset(\$doc->supertype) and \$doc->supertype == 'reply')
                  \$emit([\$doc->postId, \$doc->lastUpdate]);
              };";

      $handler = new ViewHandler("lastUpdatedPerPost");
      $handler->mapFn = $map;
      $handler->useBuiltInReduceFnCount();

      return $handler;
    }

    $doc->addHandler(lastUpdatedRepliesPerPost());


    $this->couch->saveDoc($doc);
  }


  //! @brief Configures the command.
  protected function configure() {
    $this->setName("init");
    $this->setDescription("Initializes the PitPress database, adding the required design documents.");
    $this->addArgument("documents",
      InputArgument::IS_ARRAY | InputArgument::REQUIRED,
      "The documents containing the views you want create. Use 'all' if you want insert all the documents, 'users' if
      you want just init the users or separate multiple documents with a space. The available documents are: docs, posts,
      tags, votes, scores, stars, subscriptions, classifications, badges, favorites, users, reputation, replies.");
  }


  //! @brief Executes the command.
  protected function execute(InputInterface $input, OutputInterface $output) {

    $this->mysql = $this->di['mysql'];
    $this->couch = $this->di['couchdb'];

    $documents = $input->getArgument('documents');

    // Checks if the argument 'all' is provided.
    $index = array_search("all", $documents);

    if ($index === FALSE) {

      foreach ($documents as $name)
        switch ($name) {
          case 'docs':
            $this->initDocs();
            break;

          case 'posts':
            $this->initPosts();
            break;

          case 'tags':
            $this->initTags();
            break;

          case 'votes':
            $this->initVotes();
            break;

          case 'scores':
            $this->initScores();
            break;

          case 'stars':
            $this->initStars();
            break;

          case 'subscriptions':
            $this->initSubscriptions();
            break;

          case 'classifications':
            $this->initClassifications();
            break;

          case 'badges':
            $this->initBadges();
            break;

          case 'favorites':
            $this->initFavorites();
            break;

          case 'users':
            $this->initUsers();
            break;

          case 'reputation':
            $this->initReputation();
            break;

          case 'replies':
            $this->initReplies();
            break;
        }

    }
    else
      $this->initAll();
  }

}
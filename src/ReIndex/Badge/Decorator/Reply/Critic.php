<?php

/**
 * @file Critic.php
 * @brief This file contains the Critic class.
 * @details
 * @author Filippo F. Fadda
 */


namespace ReIndex\Badge\Decorator\Reply;


use ReIndex\Badge\Decorator\Decorator;
use ReIndex\Enum\Metal;


/**
 * @brief First to leave a comment.
 * @details Awarded once.
 * @nosubgrouping
 */
class Critic extends Decorator {


  /**
   * @copydoc Decorator::getMetal()
   */
  public function getMetal() {
    return Metal::BRONZE;
  }


  /**
   * @copydoc IObserver::getMessages()
   */
  public function getMessages() {
    return ['comment'];
  }


  /**
   * @copydoc IObserver::update()
   * @todo Implements the `update()` method.
   */
  public function update($msg, $data) {

  }

}
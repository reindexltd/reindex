<?php

/**
 * @file Tag/ViewPermission.php
 * @brief This file contains the ViewPermission class.
 * @details
 * @author Filippo F. Fadda
 */


namespace ReIndex\Security\Permission\Versionable\Tag;


/**
 * @brief Permission to display information about a tag.
 */
class ViewPermission extends AbstractPermission  {


  public function getDescription() {
    return "Permission to display information about tag.";
  }


  /**
   * @brief A guest can see any current tag.
   * @retval bool
   */
  public function checkForGuest() {
    return $this->tag->state->is(State::CURRENT) ? TRUE : FALSE;
  }

}
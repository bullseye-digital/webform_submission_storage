<?php

namespace Drupal\webform_submission_storage;

/**
 * Interface StorageServiceInterface.
 */
interface StorageServiceInterface {

  /**
   * Insert submission data to custom entity.
   *
   * @param array $config
   *   Array webform config.
   * @param array $data
   *   Array submission data.
   *
   * @return int
   *   Return entity id if success.
   */
  public function storageSubmitEntity(array $config, array $data);

  /**
   * Insert submission data to custom database table.
   *
   * @param array $config
   *   Array webform config.
   * @param array $data
   *   Array submission data.
   *
   * @return int
   *   Return database row id if success.
   */
  public function storageSubmitTable(array $config, array $data);

}

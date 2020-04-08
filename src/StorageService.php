<?php

namespace Drupal\webform_submission_storage;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Class StorageService.
 */
class StorageService implements StorageServiceInterface {

  /**
   * Drupal\Core\Entity\EntityManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new StorageSerVice object.
   */
  public function __construct(EntityManagerInterface $entity_manager, Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityManager = $entity_manager;
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
  }

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
  public function storageSubmitEntity(array $config, array $data) {
    // Currently only support string, you can add 
    // other type support by override this method.
    $entity = entity_create($config['storage_key']);

    // Loop field key with value.
    foreach ($data as $key => $value) {
      $entity->$key = $value;
    }

    // Save to entity.
    try {
      $entity->save();
      if ($entity->id()) {
        if ($config['debug']) {
          $this->loggerFactory->get('webform_submission_storage') . info('Entity @entity successfully created with id: @id', [
            '@entity' => $config['storage_key'],
            '@id' => $entity->id(),
          ]);
        }
        return $entity->id();
      }
    }
    catch (EntityStorageException $e) {
      $this->loggerFactory->get('webform_submission_storage')->error('Error creating entity: @message', ['@message' => $e->getMessage()]);
    }

    return FALSE;
  }

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
  public function storageSubmitTable(array $config, array $data) {
    // Currently only support string, you can add
    // other type support by override this method.
    try {
      $id = $this->database->insert($config['storage_key'])->fields($data)->execute();
      if ($id) {
        if ($config['debug']) {
          $this->loggerFactory->get('webform_submission_storage')->info('Insert data to @table successfully created with id: @id', [
            '@table' => $config['storage_key'],
            '@id' => $id,
          ]);
        }
        return $id;
      }
    }
    catch (Exception $e) {
      $this->loggerFactory->get('webform_submission_storage')->error('Error inserting to table: @table, message: @message', [
        '@table' => $config['storage_key'],
        '@message' => $e->getMessage(),
      ]);
    }

    return FALSE;
  }

}

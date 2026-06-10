<?php

if (!defined('ABSPATH')) {
  exit;
}

class CFM_Framework_Repository
{
  public static function create_framework(string $name, string $slug, string $description = ''): int
  {
    global $wpdb;

    $table = $wpdb->prefix . 'cfm_frameworks';
    $now   = current_time('mysql');

    $existing_id = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
        $slug
      )
    );

    if ($existing_id > 0) {
      return $existing_id;
    }

    $wpdb->insert(
      $table,
      [
        'framework_uuid'    => wp_generate_uuid4(),
        'name'              => $name,
        'slug'              => $slug,
        'description'       => $description,
        'active_version_id' => null,
        'created_at'        => $now,
        'updated_at'        => $now,
      ],
      [
        '%s',
        '%s',
        '%s',
        '%s',
        '%d',
        '%s',
        '%s',
      ]
    );

    return (int) $wpdb->insert_id;
  }

  public static function create_version(int $framework_id, array $tree, string $status = 'active'): int
  {
    global $wpdb;

    $versions_table   = $wpdb->prefix . 'cfm_framework_versions';
    $frameworks_table = $wpdb->prefix . 'cfm_frameworks';
    $now              = current_time('mysql');

    $next_version = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COALESCE(MAX(version_number), 0) + 1 FROM {$versions_table} WHERE framework_id = %d",
        $framework_id
      )
    );

    $wpdb->insert(
      $versions_table,
      [
        'framework_id'    => $framework_id,
        'version_number'  => $next_version,
        'tree_json'       => wp_json_encode($tree),
        'status'          => $status,
        'compiled_at'     => null,
        'created_by'      => get_current_user_id() ?: null,
        'created_at'      => $now,
      ],
      [
        '%d',
        '%d',
        '%s',
        '%s',
        '%s',
        '%d',
        '%s',
      ]
    );

    $version_id = (int) $wpdb->insert_id;

    if ($status === 'active') {
      $wpdb->update(
        $frameworks_table,
        [
          'active_version_id' => $version_id,
          'updated_at'        => $now,
        ],
        ['id' => $framework_id],
        ['%d', '%s'],
        ['%d']
      );
    }

    return $version_id;
  }

  public static function get_framework(int $framework_id): ?object
  {
    global $wpdb;

    $table = $wpdb->prefix . 'cfm_frameworks';

    $framework = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
        $framework_id
      )
    );

    return $framework ?: null;
  }

  public static function get_active_version(int $framework_id): ?object
  {
    global $wpdb;

    $framework = self::get_framework($framework_id);

    if (!$framework || empty($framework->active_version_id)) {
      return null;
    }

    $versions_table = $wpdb->prefix . 'cfm_framework_versions';

    $version = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT *
             FROM {$versions_table}
             WHERE id = %d
             AND framework_id = %d
             LIMIT 1",
        (int) $framework->active_version_id,
        $framework_id
      )
    );

    return $version ?: null;
  }

  public static function get_versions(int $framework_id, int $limit = 0, int $offset = 0): array
  {
    global $wpdb;

    $versions_table = $wpdb->prefix . 'cfm_framework_versions';
    $limit = max(0, $limit);
    $offset = max(0, $offset);

    if ($limit > 0) {
      $versions = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT *
             FROM {$versions_table}
             WHERE framework_id = %d
             ORDER BY version_number DESC
             LIMIT %d OFFSET %d",
          $framework_id,
          $limit,
          $offset
        )
      );
    } else {
      $versions = $wpdb->get_results(
        $wpdb->prepare(
          "SELECT *
             FROM {$versions_table}
             WHERE framework_id = %d
             ORDER BY version_number DESC",
          $framework_id
        )
      );
    }

    return is_array($versions) ? $versions : [];
  }

  public static function count_versions(int $framework_id): int
  {
    global $wpdb;

    $versions_table = $wpdb->prefix . 'cfm_framework_versions';

    return (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM {$versions_table} WHERE framework_id = %d",
        $framework_id
      )
    );
  }

  public static function get_version(int $framework_id, int $version_id): ?object
  {
    global $wpdb;

    $versions_table = $wpdb->prefix . 'cfm_framework_versions';

    $version = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT *
             FROM {$versions_table}
             WHERE id = %d
             AND framework_id = %d
             LIMIT 1",
        $version_id,
        $framework_id
      )
    );

    return $version ?: null;
  }
}

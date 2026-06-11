<?php

if (!defined('ABSPATH')) {
  exit;
}

class CFM
{
  public static function init(): void
  {
    add_action('show_user_profile', [__CLASS__, 'render_user_profile_terms']);
    add_action('edit_user_profile', [__CLASS__, 'render_user_profile_terms']);
    add_action('admin_menu', [__CLASS__, 'register_assignment_admin_page']);
  }

  public static function get_framework(string $framework_slug): ?object
  {
    return CFM_Framework_Repository::get_framework_by_slug($framework_slug);
  }

  public static function get_terms(string $framework_slug): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    return CFM_Framework_Repository::get_compiled_terms((int) $framework->id);
  }

  public static function get_term_by_slug(string $framework_slug, string $term_slug): ?object
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return null;
    }

    return CFM_Framework_Repository::get_term_by_slug((int) $framework->id, $term_slug);
  }

  public static function get_descendants(string $framework_slug, string $term_slug, bool $include_self = false): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    $term = CFM_Framework_Repository::get_term_by_slug((int) $framework->id, $term_slug);

    if (!$term) {
      return [];
    }

    $uuids = CFM_Framework_Repository::get_descendant_uuids(
      (int) $framework->id,
      (string) $term->term_uuid,
      null,
      $include_self
    );

    return CFM_Framework_Repository::get_terms_by_uuids((int) $framework->id, $uuids);
  }

  public static function get_ancestors(string $framework_slug, string $term_slug, bool $include_self = false): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    $term = CFM_Framework_Repository::get_term_by_slug((int) $framework->id, $term_slug);

    if (!$term) {
      return [];
    }

    $uuids = CFM_Framework_Repository::get_ancestor_uuids(
      (int) $framework->id,
      (string) $term->term_uuid,
      null,
      $include_self
    );

    return CFM_Framework_Repository::get_terms_by_uuids((int) $framework->id, $uuids);
  }

  public static function get_siblings(string $framework_slug, string $term_slug, bool $include_self = false): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    $term = CFM_Framework_Repository::get_term_by_slug((int) $framework->id, $term_slug);

    if (!$term) {
      return [];
    }

    return CFM_Framework_Repository::get_sibling_terms(
      (int) $framework->id,
      (string) $term->term_uuid,
      null,
      $include_self
    );
  }

  public static function get_user_terms(int $user_id, string $framework_slug, string $context = 'profile'): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    return CFM_Framework_Repository::get_user_terms($user_id, (int) $framework->id, $context);
  }

  public static function get_user_effective_terms(int $user_id, string $framework_slug, string $context = 'profile'): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    $assigned_uuids = CFM_Framework_Repository::get_user_term_uuids($user_id, (int) $framework->id, $context);

    if (empty($assigned_uuids)) {
      return [];
    }

    $effective_uuids = [];

    foreach ($assigned_uuids as $assigned_uuid) {
      $assigned_uuid = (string) $assigned_uuid;

      if ($assigned_uuid === '') {
        continue;
      }

      $effective_uuids[] = $assigned_uuid;

      $ancestor_uuids = CFM_Framework_Repository::get_ancestor_uuids(
        (int) $framework->id,
        $assigned_uuid,
        null,
        false
      );

      foreach ($ancestor_uuids as $ancestor_uuid) {
        $effective_uuids[] = (string) $ancestor_uuid;
      }
    }

    $effective_uuids = array_values(array_unique(array_filter($effective_uuids)));

    return self::order_terms_as_tree(
      CFM_Framework_Repository::get_terms_by_uuids((int) $framework->id, $effective_uuids)
    );
  }

  public static function get_user_term_uuids(int $user_id, string $framework_slug, string $context = 'profile'): array
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return [];
    }

    return CFM_Framework_Repository::get_user_term_uuids($user_id, (int) $framework->id, $context);
  }

  public static function set_user_terms(int $user_id, string $framework_slug, array $term_uuids, string $context = 'profile'): bool
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return false;
    }

    return CFM_Framework_Repository::set_user_terms($user_id, (int) $framework->id, $term_uuids, $context);
  }

  public static function user_has_term(int $user_id, string $framework_slug, string $term_slug_or_uuid, string $context = 'profile', bool $effective = true): bool
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return false;
    }

    $term_uuid = self::resolve_term_uuid((int) $framework->id, $term_slug_or_uuid);

    if ($term_uuid === '') {
      return false;
    }

    if (!$effective) {
      return CFM_Framework_Repository::user_has_term($user_id, (int) $framework->id, $term_uuid, $context);
    }

    $effective_terms = self::get_user_effective_terms_by_framework_id($user_id, (int) $framework->id, $context);

    foreach ($effective_terms as $effective_term) {
      if ((string) $effective_term->term_uuid === $term_uuid) {
        return true;
      }
    }

    return false;
  }

  public static function count_users(string $framework_slug, string $term_slug_or_uuid, string $context = 'profile', bool $include_descendants = true): int
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return 0;
    }

    $term_uuid = self::resolve_term_uuid((int) $framework->id, $term_slug_or_uuid);

    if ($term_uuid === '') {
      return 0;
    }

    return CFM_Framework_Repository::count_users_for_term(
      (int) $framework->id,
      $term_uuid,
      $context,
      $include_descendants
    );
  }

  public static function matches(int $user_id, string $framework_slug, array $term_slugs_or_uuids, string $context = 'profile', bool $include_descendants = true): bool
  {
    $framework = self::get_framework($framework_slug);

    if (!$framework) {
      return false;
    }

    $term_uuids = [];

    foreach ($term_slugs_or_uuids as $term_slug_or_uuid) {
      $term_uuid = self::resolve_term_uuid((int) $framework->id, (string) $term_slug_or_uuid);

      if ($term_uuid !== '') {
        $term_uuids[] = $term_uuid;
      }
    }

    return CFM_Framework_Repository::user_matches_any_term(
      $user_id,
      (int) $framework->id,
      $term_uuids,
      $context,
      $include_descendants
    );
  }

  public static function register_assignment_admin_page(): void
  {
    add_users_page(
      'Community Framework Assignments',
      'Framework Assignments',
      'list_users',
      'cfm-framework-assignments',
      [__CLASS__, 'render_assignment_admin_page']
    );

    add_users_page(
      'CFM Segmentation Tests',
      'CFM Segmentation Tests',
      'list_users',
      'cfm-segmentation-tests',
      [__CLASS__, 'render_segmentation_tests_admin_page']
    );

    add_users_page(
      'Profile Statistics',
      'Profile Statistics',
      'list_users',
      'cfm-profile-statistics',
      [__CLASS__, 'render_profile_statistics_admin_page']
    );
  }

  public static function render_user_profile_terms(object $user): void
  {
    if (!current_user_can('edit_user', (int) $user->ID)) {
      return;
    }

    $frameworks = self::get_profile_frameworks();

    if (empty($frameworks)) {
      return;
    }

    echo '<h2>Community Framework Assignments</h2>';
    echo '<table class="form-table" role="presentation">';

    foreach ($frameworks as $framework) {
      $assigned_terms = CFM_Framework_Repository::get_user_terms((int) $user->ID, (int) $framework->id);
      $assigned_labels = array_map(static fn($term): string => (string) $term->label, $assigned_terms);
      $effective_terms = self::get_user_effective_terms_by_framework_id((int) $user->ID, (int) $framework->id);
      $effective_labels = array_map(static fn($term): string => (string) $term->label, $effective_terms);
      $manage_url = self::get_assignment_admin_url((int) $user->ID, (int) $framework->id);

      echo '<tr>';
      echo '<th><label>' . esc_html($framework->name) . '</label></th>';
      echo '<td>';

      if (empty($assigned_labels)) {
        echo '<p><strong>Assigned terms:</strong> None.</p>';
      } else {
        echo '<p><strong>Assigned terms:</strong> ' . esc_html(implode(', ', $assigned_labels)) . '</p>';
      }

      if (!empty($effective_labels)) {
        echo '<p><strong>Effective inherited terms:</strong> ' . esc_html(implode(', ', $effective_labels)) . '</p>';
      }

      if (current_user_can('list_users')) {
        echo '<p><a class="button" href="' . esc_url($manage_url) . '">Manage framework assignments</a></p>';
      }

      echo '<p class="description">Assignments are stored by stable term UUID and read through compiled framework tables.</p>';
      echo '</td>';
      echo '</tr>';
    }

    echo '</table>';
  }

  public static function render_segmentation_tests_admin_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die('You do not have permission to run segmentation tests.');
    }

    $frameworks = self::get_profile_frameworks();

    echo '<div class="wrap">';
    echo '<h1>CFM Segmentation Tests</h1>';
    echo '<p>This page verifies assignment, inheritance, counts, and matching against compiled framework data.</p>';

    if (empty($frameworks)) {
      echo '<div class="notice notice-warning inline"><p>No compiled frameworks are available.</p></div>';
      echo '</div>';
      return;
    }

    $selected_framework_id = isset($_REQUEST['framework_id']) ? absint($_REQUEST['framework_id']) : (int) $frameworks[0]->id;
    $selected_user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;
    $term_query = isset($_REQUEST['term_query']) ? sanitize_title(wp_unslash($_REQUEST['term_query'])) : '';
    $user_search = isset($_REQUEST['user_search']) ? sanitize_text_field(wp_unslash($_REQUEST['user_search'])) : '';

    if (!self::framework_id_exists($frameworks, $selected_framework_id)) {
      $selected_framework_id = (int) $frameworks[0]->id;
    }

    $selected_framework = CFM_Framework_Repository::get_framework($selected_framework_id);
    $selected_user = $selected_user_id > 0 ? get_userdata($selected_user_id) : false;
    $framework_slug = $selected_framework ? (string) $selected_framework->slug : '';
    $terms = $selected_framework ? self::order_terms_as_tree(CFM_Framework_Repository::get_compiled_terms($selected_framework_id)) : [];
    $candidate_users = self::get_assignment_candidate_users($user_search, $selected_user_id);
    $prechecked_user_id = $selected_user_id;

    if ($prechecked_user_id <= 0 && count($candidate_users['users']) === 1 && !$candidate_users['too_many'] && !$candidate_users['too_short']) {
      $prechecked_user_id = (int) $candidate_users['users'][0]->ID;
    }

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="cfm-segmentation-tests" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="cfm_seg_framework_id">Framework</label></th><td>';
    echo '<select id="cfm_seg_framework_id" name="framework_id">';

    foreach ($frameworks as $framework) {
      echo '<option value="' . esc_attr((string) $framework->id) . '" ' . selected($selected_framework_id, (int) $framework->id, false) . '>' . esc_html($framework->name) . '</option>';
    }

    echo '</select></td></tr>';
    echo '<tr><th scope="row"><label for="cfm_seg_user_search">Find user</label></th><td>';
    echo '<input type="search" id="cfm_seg_user_search" name="user_search" value="' . esc_attr($user_search) . '" class="regular-text" placeholder="At least 3 characters, or exact email" /> ';
    submit_button('Search Users', 'secondary', '', false);
    echo ' <a class="button" href="' . esc_url(admin_url('users.php?page=cfm-segmentation-tests')) . '">Clear</a>';
    echo '<p class="description">Select a user to inspect assigned and effective terms.</p>';
    echo '</td></tr>';
    echo '<tr><th scope="row">User</th><td>';

    if ($candidate_users['too_short']) {
      echo '<p class="description">Enter at least 3 characters, or search for an exact email address.</p>';
    } elseif ($candidate_users['too_many']) {
      echo '<div class="notice notice-warning inline"><p>More than 25 users matched. Refine the search.</p></div>';
    } elseif (empty($candidate_users['users'])) {
      echo '<p>No matching users selected.</p>';
    } else {
      echo '<fieldset style="max-width:760px;">';
      echo '<legend class="screen-reader-text">Select user</legend>';

      foreach ($candidate_users['users'] as $user) {
        $label = sprintf('%s (%s)', $user->display_name ?: $user->user_login, $user->user_login);
        echo '<label style="display:block;margin:8px 0;padding:10px 12px;background:#fff;border:1px solid #ccd0d4;">';
        echo '<input type="radio" name="user_id" value="' . esc_attr((string) $user->ID) . '" ' . checked($prechecked_user_id, (int) $user->ID, false) . ' /> ';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<span class="description">' . esc_html($user->user_email) . ' &nbsp; ID: ' . esc_html((string) $user->ID) . '</span>';
        echo '</label>';
      }

      echo '</fieldset>';
    }

    echo '</td></tr>';
    echo '<tr><th scope="row"><label for="cfm_seg_term_query">Test term</label></th><td>';
    echo '<input type="text" id="cfm_seg_term_query" name="term_query" value="' . esc_attr($term_query) . '" class="regular-text" placeholder="Example: elementary, grade-1, math" />';
    echo '<p class="description">Enter a term slug to test user matching and user counts.</p>';
    echo '</td></tr>';
    echo '</tbody></table>';
    submit_button('Run Tests', 'primary', '', false);
    echo '</form>';

    if (!$selected_framework) {
      echo '<div class="notice notice-error inline"><p>Invalid framework.</p></div>';
      echo '</div>';
      return;
    }

    echo '<hr />';
    echo '<h2>Framework</h2>';
    echo '<p><strong>' . esc_html((string) $selected_framework->name) . '</strong> <code>' . esc_html($framework_slug) . '</code></p>';

    echo '<p class="description">Use Profile Statistics for population counts and distribution reports.</p>';

    echo '<h2>User Term Resolution</h2>';
    if ($selected_user_id <= 0 || !$selected_user) {
      echo '<p>Select a user above, then run tests to inspect assignments.</p>';
    } else {
      $assigned_terms = self::get_user_terms($selected_user_id, $framework_slug);
      $effective_terms = self::get_user_effective_terms($selected_user_id, $framework_slug);
      $user_label = sprintf('%s (%s) — %s', $selected_user->display_name ?: $selected_user->user_login, $selected_user->user_login, $selected_user->user_email);

      echo '<p><strong>User:</strong> ' . esc_html($user_label) . '</p>';
      echo '<p><strong>Assigned terms:</strong> ' . esc_html(self::format_term_labels($assigned_terms)) . '</p>';
      echo '<p><strong>Effective inherited terms:</strong> ' . esc_html(self::format_term_labels($effective_terms)) . '</p>';

      if ($term_query !== '') {
        $custom_term = self::get_term_by_slug($framework_slug, $term_query);
        $custom_user_has = self::user_has_term($selected_user_id, $framework_slug, $term_query);
        $custom_explicit_count = self::count_users($framework_slug, $term_query, 'profile', false);
        $custom_effective_count = self::count_users($framework_slug, $term_query, 'profile', true);

        echo '<h2>Custom Test Term Results</h2>';
        echo '<p><strong>Test term:</strong> <code>' . esc_html($term_query) . '</code></p>';

        if (!$custom_term) {
          echo '<div class="notice notice-warning inline"><p>No compiled term with this slug exists in the selected framework. Counts and matching should be treated as invalid/zero.</p></div>';
        }

        echo '<table class="widefat striped" style="max-width:760px;"><thead><tr><th>Question</th><th>Result</th></tr></thead><tbody>';
        echo '<tr><td>Selected user has this term? <code>user_has_term(' . esc_html((string) $selected_user_id) . ', ' . esc_html($framework_slug) . ', ' . esc_html($term_query) . ')</code></td><td><strong>' . esc_html($custom_user_has ? 'true' : 'false') . '</strong></td></tr>';
        echo '<tr><td>Explicit user count</td><td><strong>' . esc_html((string) $custom_explicit_count) . '</strong></td></tr>';
        echo '<tr><td>Descendant-aware user count</td><td><strong>' . esc_html((string) $custom_effective_count) . '</strong></td></tr>';
        echo '</tbody></table>';
      }

      echo '<h2>Baseline Matching Checks</h2>';
      echo '<table class="widefat striped" style="max-width:960px;"><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>';

      $checks = [];
      foreach (['grade-1', 'elementary', 'math'] as $slug) {
        if (self::get_term_by_slug($framework_slug, $slug)) {
          $checks[] = [
            'label' => 'user_has_term(' . $selected_user_id . ', ' . $framework_slug . ', ' . $slug . ')',
            'result' => self::user_has_term($selected_user_id, $framework_slug, $slug) ? 'true' : 'false',
          ];
        }
      }

      if (empty($checks)) {
        echo '<tr><td colspan="2">No matching checks available for this framework yet.</td></tr>';
      } else {
        foreach ($checks as $check) {
          $is_true = $check['result'] === 'true' || (is_numeric($check['result']) && (int) $check['result'] > 0);
          echo '<tr>';
          echo '<td><code>' . esc_html($check['label']) . '</code></td>';
          echo '<td><strong>' . esc_html($check['result']) . '</strong> ' . ($is_true ? '<span style="color:#008a20;">PASS</span>' : '<span style="color:#8a1f11;">NONE/FALSE</span>') . '</td>';
          echo '</tr>';
        }
      }

      echo '</tbody></table>';
    }

    echo '<p class="description">This page reads compiled tables and helper APIs only. It does not modify assignments.</p>';
    echo '</div>';
  }

  public static function render_profile_statistics_admin_page(): void
  {
    if (!current_user_can('list_users')) {
      wp_die('You do not have permission to view profile statistics.');
    }

    $frameworks = self::get_profile_frameworks();

    echo '<div class="wrap">';
    echo '<h1>Community Profile Snapshot</h1>';
    echo '<p>This page summarizes user profile composition across compiled Community Framework terms.</p>';

    if (empty($frameworks)) {
      echo '<div class="notice notice-warning inline"><p>No compiled frameworks are available.</p></div>';
      echo '</div>';
      return;
    }

    $selected_framework_id = isset($_GET['framework_id']) ? absint($_GET['framework_id']) : (int) $frameworks[0]->id;
    $term_query = isset($_GET['term_query']) ? sanitize_title(wp_unslash($_GET['term_query'])) : '';
    $sort = isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'tree';
    $dir = isset($_GET['dir']) ? strtolower(sanitize_key(wp_unslash($_GET['dir']))) : 'asc';

    if (!in_array($sort, ['tree', 'slug', 'users'], true)) {
      $sort = 'tree';
    }

    if (!in_array($dir, ['asc', 'desc'], true)) {
      $dir = 'asc';
    }

    if (!self::framework_id_exists($frameworks, $selected_framework_id)) {
      $selected_framework_id = (int) $frameworks[0]->id;
    }

    $selected_framework = CFM_Framework_Repository::get_framework($selected_framework_id);

    if (!$selected_framework) {
      echo '<div class="notice notice-error inline"><p>Invalid framework.</p></div>';
      echo '</div>';
      return;
    }

    $framework_slug = (string) $selected_framework->slug;
    $terms = self::order_terms_as_tree(CFM_Framework_Repository::get_compiled_terms($selected_framework_id));

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="cfm-profile-statistics" />';
    echo '<input type="hidden" name="sort" value="' . esc_attr($sort) . '" />';
    echo '<input type="hidden" name="dir" value="' . esc_attr($dir) . '" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="cfm_stats_framework_id">Profile</label></th><td>';
    echo '<select id="cfm_stats_framework_id" name="framework_id">';

    foreach ($frameworks as $framework) {
      echo '<option value="' . esc_attr((string) $framework->id) . '" ' . selected($selected_framework_id, (int) $framework->id, false) . '>' . esc_html($framework->name) . '</option>';
    }

    echo '</select></td></tr>';
    echo '<tr><th scope="row"><label for="cfm_stats_term_query">Search term</label></th><td>';
    echo '<input type="text" id="cfm_stats_term_query" name="term_query" value="' . esc_attr($term_query) . '" class="regular-text" placeholder="Example: elementary, grade-1, math" />';
    echo '<p class="description">Optional. Enter a term slug to inspect audience size.</p>';
    echo '</td></tr>';
    echo '</tbody></table>';
    submit_button('View Snapshot', 'primary', '', false);
    echo '</form>';

    echo '<hr />';
    echo '<h2>Profiles</h2>';
    echo '<p><strong>' . esc_html((string) $selected_framework->name) . '</strong> <code>' . esc_html($framework_slug) . '</code></p>';

    if (empty($terms)) {
      echo '<p>No compiled terms available.</p>';
      echo '</div>';
      return;
    }

    global $wpdb;
    $users_table = $wpdb->users;
    $user_terms_table = $wpdb->prefix . 'cfm_user_terms';

    $total_users = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$users_table}");
    $profiled_users = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$user_terms_table} WHERE framework_id = %d AND context = %s",
        $selected_framework_id,
        'profile'
      )
    );

    $rows = [];
    $axis_rows = [];

    foreach ($terms as $term) {
      $slug = (string) $term->slug;
      $users_count = self::count_users($framework_slug, $slug, 'profile', true);
      $pct = $profiled_users > 0 ? round(($users_count / $profiled_users) * 100, 1) : 0;

      $row = [
        'term' => $term,
        'users_count' => $users_count,
        'pct' => $pct,
      ];

      $rows[] = $row;

      if ((int) $term->depth === 0) {
        $axis_rows[] = $row;
      }
    }

    echo '<p style="font-size:14px;line-height:1.7;margin-top:10px;">';
    echo '<strong>Total Users:</strong> ' . esc_html((string) $total_users) . '<br />';
    echo '<strong>Profile Categories:</strong> ' . esc_html((string) count($terms)) . '<br />';

    foreach ($axis_rows as $axis_row) {
      $axis_term = $axis_row['term'];
      echo '<strong>' . esc_html((string) $axis_term->label) . ':</strong> ' . esc_html((string) $axis_row['users_count']) . ' users<br />';
    }

    echo '</p>';

    $search_row = null;
    $search_term = null;

    if ($term_query !== '') {
      $search_term = self::get_term_by_slug($framework_slug, $term_query);

      if ($search_term) {
        $users_count = self::count_users($framework_slug, $term_query, 'profile', true);
        $pct = $profiled_users > 0 ? round(($users_count / $profiled_users) * 100, 1) : 0;
        $search_row = [
          'term' => $search_term,
          'users_count' => $users_count,
          'pct' => $pct,
        ];
      }
    }

    $display_rows = $rows;

    if ($sort === 'slug') {
      usort($display_rows, static function ($a, $b) use ($dir) {
        $result = strcasecmp((string) $a['term']->slug, (string) $b['term']->slug);
        return $dir === 'desc' ? -$result : $result;
      });
    } elseif ($sort === 'users') {
      usort($display_rows, static function ($a, $b) use ($dir) {
        $result = ((int) $a['users_count']) <=> ((int) $b['users_count']);
        if ($result === 0) {
          $result = strcasecmp((string) $a['term']->path, (string) $b['term']->path);
        }
        return $dir === 'desc' ? -$result : $result;
      });
    }

    $base_args = [
      'page' => 'cfm-profile-statistics',
      'framework_id' => $selected_framework_id,
    ];

    if ($term_query !== '') {
      $base_args['term_query'] = $term_query;
    }

    $tree_url = esc_url(add_query_arg($base_args, admin_url('users.php')));
    $slug_dir = ($sort === 'slug' && $dir === 'asc') ? 'desc' : 'asc';
    $users_dir = ($sort === 'users' && $dir === 'desc') ? 'asc' : 'desc';
    $slug_url = esc_url(add_query_arg(array_merge($base_args, ['sort' => 'slug', 'dir' => $slug_dir]), admin_url('users.php')));
    $users_url = esc_url(add_query_arg(array_merge($base_args, ['sort' => 'users', 'dir' => $users_dir]), admin_url('users.php')));

    echo '<p class="description">User counts include inherited matches. Example: a user assigned to Grade 1 also counts under Elementary and Grade Level.</p>';

    if ($term_query !== '' && !$search_term) {
      echo '<div class="notice notice-warning inline"><p>No compiled term with slug <code>' . esc_html($term_query) . '</code> exists in this profile.</p></div>';
    }

    echo '<div style="max-height:620px;overflow:auto;border:1px solid #ccd0d4;background:#fff;max-width:1050px;">';
    echo '<table class="widefat striped" style="border:0;">';
    echo '<thead><tr>';
    echo '<th><a href="' . $tree_url . '">Profile Category</a></th>';
    echo '<th><a href="' . $slug_url . '">Slug</a></th>';
    echo '<th><a href="' . $users_url . '">Users</a></th>';
    echo '<th>%</th>';
    echo '</tr></thead><tbody>';

    if ($search_row) {
      $term = $search_row['term'];
      $indent = str_repeat('&mdash; ', max(0, (int) $term->depth));
      echo '<tr style="background:#eaf3ff;font-weight:600;">';
      echo '<td>Search: ' . wp_kses_post($indent) . esc_html((string) $term->label) . '</td>';
      echo '<td><code>' . esc_html((string) $term->slug) . '</code></td>';
      echo '<td>' . esc_html((string) $search_row['users_count']) . '</td>';
      echo '<td>' . esc_html((string) $search_row['pct']) . '%</td>';
      echo '</tr>';
    }

    if ($search_row) {
      echo '<tr><td colspan="4" style="height:10px;background:#fff;border-top:1px solid #ccd0d4;border-bottom:1px solid #ccd0d4;"></td></tr>';
    }

    foreach ($display_rows as $row) {
      $term = $row['term'];
      $depth = (int) $term->depth;
      $indent = $sort === 'tree' ? str_repeat('&mdash; ', max(0, $depth)) : '';
      $is_axis = $depth === 0;

      echo '<tr' . ($is_axis && $sort === 'tree' ? ' style="font-weight:600;"' : '') . '>';
      echo '<td>' . wp_kses_post($indent) . esc_html((string) $term->label) . '</td>';
      echo '<td><code>' . esc_html((string) $term->slug) . '</code></td>';
      echo '<td>' . esc_html((string) $row['users_count']) . '</td>';
      echo '<td>' . esc_html((string) $row['pct']) . '%</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
    echo '<p class="description">Click Profile Category to restore tree order. Slug sorts alphabetically. Users sorts by audience size. The highlighted search row stays pinned above the table.</p>';
    echo '<p class="description">This page is analytics/read-only. Assignment changes still happen under Users → Framework Assignments.</p>';
    echo '</div>';
  }

  public static function render_assignment_admin_page(): void
  {
    if (!current_user_can('list_users')) {
      wp_die('You do not have permission to manage framework assignments.');
    }

    $frameworks = self::get_profile_frameworks();

    if (empty($frameworks)) {
      echo '<div class="wrap"><h1>Community Framework Assignments</h1><p>No compiled frameworks are available.</p></div>';
      return;
    }

    $selected_framework_id = isset($_REQUEST['framework_id']) ? absint($_REQUEST['framework_id']) : (int) $frameworks[0]->id;
    $selected_user_id = isset($_REQUEST['user_id']) ? absint($_REQUEST['user_id']) : 0;

    if (!self::framework_id_exists($frameworks, $selected_framework_id)) {
      $selected_framework_id = (int) $frameworks[0]->id;
    }

    $notice = '';
    $notice_type = 'success';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cfm_assignment_action']) && $_POST['cfm_assignment_action'] === 'save') {
      $nonce = isset($_POST['cfm_assignment_nonce']) ? sanitize_text_field(wp_unslash($_POST['cfm_assignment_nonce'])) : '';

      if (!wp_verify_nonce($nonce, 'cfm_save_assignments')) {
        $notice = 'Assignment save failed: invalid security token.';
        $notice_type = 'error';
      } elseif ($selected_user_id <= 0 || !get_userdata($selected_user_id) || !current_user_can('edit_user', $selected_user_id)) {
        $notice = 'Assignment save failed: invalid or inaccessible user.';
        $notice_type = 'error';
      } elseif (!self::framework_id_exists($frameworks, $selected_framework_id)) {
        $notice = 'Assignment save failed: invalid framework.';
        $notice_type = 'error';
      } else {
        $posted_terms = isset($_POST['cfm_user_terms']) && is_array($_POST['cfm_user_terms'])
          ? array_map('sanitize_text_field', wp_unslash($_POST['cfm_user_terms']))
          : [];

        $saved = CFM_Framework_Repository::set_user_terms($selected_user_id, $selected_framework_id, $posted_terms);

        if ($saved) {
          $saved_user = get_userdata($selected_user_id);
          $saved_framework = CFM_Framework_Repository::get_framework($selected_framework_id);
          $saved_user_label = $saved_user ? (($saved_user->display_name ?: $saved_user->user_login) . ' / ' . $saved_user->user_email) : ('User ID ' . $selected_user_id);
          $saved_framework_label = $saved_framework ? (string) $saved_framework->name : ('Framework ID ' . $selected_framework_id);
          $notice = 'Framework assignments saved for ' . $saved_user_label . ' in ' . $saved_framework_label . '.';
        } else {
          $notice = 'Assignment save failed.';
          $notice_type = 'error';
        }
      }
    }

    $user_search = isset($_REQUEST['user_search']) ? sanitize_text_field(wp_unslash($_REQUEST['user_search'])) : '';
    $user_search_result = self::get_assignment_candidate_users($user_search, $selected_user_id);
    $users = $user_search_result['users'];
    $user_search_too_many = (bool) $user_search_result['too_many'];
    $user_search_too_short = (bool) $user_search_result['too_short'];

    // Do not auto-load assignments from search results.
    // Even a single match should require the admin to click Load so the selected user is explicit.
    $prechecked_user_id = $selected_user_id;

    if ($prechecked_user_id <= 0 && count($users) === 1 && !$user_search_too_many && !$user_search_too_short) {
      $prechecked_user_id = (int) $users[0]->ID;
    }

    $selected_framework = CFM_Framework_Repository::get_framework($selected_framework_id);
    $selected_user = $selected_user_id > 0 ? get_userdata($selected_user_id) : false;
    $terms = $selected_framework ? self::order_terms_as_tree(CFM_Framework_Repository::get_compiled_terms($selected_framework_id)) : [];
    $assigned = $selected_user_id > 0 ? CFM_Framework_Repository::get_user_term_uuids($selected_user_id, $selected_framework_id) : [];

    echo '<div class="wrap">';
    echo '<h1>Community Framework Assignments</h1>';

    if ($notice !== '') {
      echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
    }

    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="cfm-framework-assignments" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row"><label for="cfm_user_search">Find user</label></th><td>';
    echo '<input type="search" id="cfm_user_search" name="user_search" value="' . esc_attr($user_search) . '" class="regular-text" placeholder="At least 3 characters, or exact email" /> ';
    submit_button('Search', 'secondary', '', false);
    echo ' <a class="button" href="' . esc_url(admin_url('users.php?page=cfm-framework-assignments')) . '">Clear / New Search</a>';
    echo '<p class="description">Search by login, email, or display name. Results are limited to 25 users. Select a user, then click Load.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">User</th><td>';

    if ($user_search_too_short) {
      echo '<p class="description">Enter at least 3 characters, or search for an exact email address.</p>';
    } elseif ($user_search_too_many) {
      echo '<div class="notice notice-warning inline"><p>More than 25 users matched. Refine the search.</p></div>';
    } elseif (empty($users)) {
      echo '<p>No matching users.</p>';
    } else {
      echo '<fieldset style="max-width:760px;">';
      echo '<legend class="screen-reader-text">Select user</legend>';

      foreach ($users as $user) {
        $label = sprintf('%s (%s)', $user->display_name ?: $user->user_login, $user->user_login);
        echo '<label style="display:block;margin:8px 0;padding:10px 12px;background:#fff;border:1px solid #ccd0d4;">';
        echo '<input type="radio" name="user_id" value="' . esc_attr((string) $user->ID) . '" ' . checked($prechecked_user_id, (int) $user->ID, false) . ' /> ';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<span class="description">' . esc_html($user->user_email) . ' &nbsp; ID: ' . esc_html((string) $user->ID) . '</span>';
        echo '</label>';
      }

      echo '</fieldset>';
    }

    echo '</td></tr>';
    echo '<tr><th scope="row"><label for="cfm_framework_id">Framework</label></th><td>';
    echo '<select id="cfm_framework_id" name="framework_id">';

    foreach ($frameworks as $framework) {
      echo '<option value="' . esc_attr((string) $framework->id) . '" ' . selected($selected_framework_id, (int) $framework->id, false) . '>' . esc_html($framework->name) . '</option>';
    }

    echo '</select> ';
    submit_button('Load', 'secondary', '', false);
    echo '</td></tr>';
    echo '</tbody></table>';
    echo '</form>';

    if ($selected_user_id <= 0) {
      echo '<p>Select a user from the search results, then click Load to manage assignments.</p>';
      echo '</div>';
      return;
    }

    if (!$selected_user || !$selected_framework) {
      echo '<div class="notice notice-error inline"><p>Cannot load assignments: invalid user or framework.</p></div>';
      echo '</div>';
      return;
    }

    $managing_label = sprintf(
      '%s (%s) — %s',
      $selected_user->display_name ?: $selected_user->user_login,
      $selected_user->user_login,
      $selected_user->user_email
    );

    echo '<div class="notice notice-info inline"><p><strong>Currently managing:</strong> ' . esc_html($managing_label) . ' / <strong>Framework:</strong> ' . esc_html((string) $selected_framework->name) . '</p></div>';
    echo '<hr />';
    echo '<form method="post" action="' . esc_url(admin_url('users.php?page=cfm-framework-assignments')) . '">';
    wp_nonce_field('cfm_save_assignments', 'cfm_assignment_nonce');
    echo '<input type="hidden" name="cfm_assignment_action" value="save" />';
    echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $selected_user_id) . '" />';
    echo '<input type="hidden" name="framework_id" value="' . esc_attr((string) $selected_framework_id) . '" />';

    echo '<h2>' . esc_html($selected_framework ? $selected_framework->name : 'Framework') . '</h2>';

    if (empty($terms)) {
      echo '<p>No compiled terms are available for this framework.</p>';
    } else {
      echo '<div style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:12px 16px;">';

      foreach ($terms as $term) {
        $uuid = (string) $term->term_uuid;
        $depth = max(0, (int) $term->depth);
        $margin = 24 * $depth;

        if ($depth === 0) {
          echo '<div style="margin:12px 0 6px ' . esc_attr((string) $margin) . 'px;font-weight:600;">';
          echo esc_html($term->label) . ' <code>' . esc_html($term->slug) . '</code>';
          echo '</div>';
          continue;
        }

        $checked = checked(in_array($uuid, $assigned, true), true, false);

        echo '<label style="display:block;margin:5px 0 5px ' . esc_attr((string) $margin) . 'px;">';
        echo '<input type="checkbox" name="cfm_user_terms[]" value="' . esc_attr($uuid) . '" ' . $checked . ' /> ';
        echo esc_html($term->label) . ' <code>' . esc_html($term->slug) . '</code>';
        echo '</label>';
      }

      echo '</div>';

      $assigned_terms = $selected_user_id > 0
        ? CFM_Framework_Repository::get_user_terms($selected_user_id, $selected_framework_id)
        : [];
      $effective_terms = $selected_user_id > 0
        ? self::get_user_effective_terms_by_framework_id($selected_user_id, $selected_framework_id)
        : [];

      echo '<div style="max-width:760px;margin-top:12px;">';
      echo '<p><strong>Assigned terms:</strong> ' . esc_html(self::format_term_labels($assigned_terms)) . '</p>';
      echo '<p><strong>Effective inherited terms:</strong> ' . esc_html(self::format_term_labels($effective_terms)) . '</p>';
      echo '</div>';

      echo '<p class="description">Only explicit user choices are stored. Ancestors are inherited at query time through compiled closure tables.</p>';
      submit_button('Save Assignments', 'primary', 'submit', false);
      echo ' <a class="button" href="' . esc_url(admin_url('users.php?page=cfm-framework-assignments')) . '">Clear / New Search</a>';
    }

    echo '</form>';
    echo '</div>';
  }

  private static function get_assignment_candidate_users(string $search, int $selected_user_id = 0): array
  {
    $search = trim($search);
    $users_by_id = [];
    $too_many = false;
    $too_short = false;

    if ($search !== '') {
      $is_exact_email = is_email($search);

      if (!$is_exact_email && mb_strlen($search) < 3) {
        $too_short = true;
      } else {
        $query_args = [
          'number' => 26,
          'orderby' => 'display_name',
          'order' => 'ASC',
          'fields' => ['ID', 'display_name', 'user_login', 'user_email'],
        ];

        if ($is_exact_email) {
          $query_args['search'] = $search;
          $query_args['search_columns'] = ['user_email'];
        } else {
          $query_args['search'] = '*' . $search . '*';
          $query_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $query = new WP_User_Query($query_args);
        $results = $query->get_results();

        if (count($results) > 25) {
          $too_many = true;
          $results = array_slice($results, 0, 25);
        }

        foreach ($results as $user) {
          $users_by_id[(int) $user->ID] = $user;
        }
      }
    }

    if ($selected_user_id > 0) {
      $selected_user = get_userdata($selected_user_id);

      if ($selected_user) {
        $users_by_id[(int) $selected_user->ID] = (object) [
          'ID' => (int) $selected_user->ID,
          'display_name' => (string) $selected_user->display_name,
          'user_login' => (string) $selected_user->user_login,
          'user_email' => (string) $selected_user->user_email,
        ];
      }
    }

    $users = array_values($users_by_id);

    usort($users, static function ($a, $b): int {
      $a_label = (string) ($a->display_name ?: $a->user_login);
      $b_label = (string) ($b->display_name ?: $b->user_login);

      $label_compare = strcasecmp($a_label, $b_label);

      if ($label_compare !== 0) {
        return $label_compare;
      }

      return ((int) $a->ID) <=> ((int) $b->ID);
    });

    return [
      'users' => $users,
      'too_many' => $too_many,
      'too_short' => $too_short,
    ];
  }

  private static function get_user_effective_terms_by_framework_id(int $user_id, int $framework_id, string $context = 'profile'): array
  {
    $assigned_uuids = CFM_Framework_Repository::get_user_term_uuids($user_id, $framework_id, $context);

    if (empty($assigned_uuids)) {
      return [];
    }

    $effective_uuids = [];

    foreach ($assigned_uuids as $assigned_uuid) {
      $assigned_uuid = (string) $assigned_uuid;

      if ($assigned_uuid === '') {
        continue;
      }

      $effective_uuids[] = $assigned_uuid;

      $ancestor_uuids = CFM_Framework_Repository::get_ancestor_uuids(
        $framework_id,
        $assigned_uuid,
        null,
        false
      );

      foreach ($ancestor_uuids as $ancestor_uuid) {
        $effective_uuids[] = (string) $ancestor_uuid;
      }
    }

    $effective_uuids = array_values(array_unique(array_filter($effective_uuids)));

    return self::order_terms_as_tree(
      CFM_Framework_Repository::get_terms_by_uuids($framework_id, $effective_uuids)
    );
  }

  private static function format_term_labels(array $terms): string
  {
    if (empty($terms)) {
      return 'None.';
    }

    $labels = array_map(static fn($term): string => (string) $term->label, $terms);

    return implode(', ', $labels);
  }

  private static function get_assignment_admin_url(int $user_id, int $framework_id): string
  {
    return add_query_arg(
      [
        'page' => 'cfm-framework-assignments',
        'user_id' => $user_id,
        'framework_id' => $framework_id,
      ],
      admin_url('users.php')
    );
  }

  private static function framework_id_exists(array $frameworks, int $framework_id): bool
  {
    foreach ($frameworks as $framework) {
      if ((int) $framework->id === $framework_id) {
        return true;
      }
    }

    return false;
  }

  private static function order_terms_as_tree(array $terms): array
  {
    if (empty($terms)) {
      return [];
    }

    $children_by_parent = [];

    foreach ($terms as $term) {
      $parent_uuid = isset($term->parent_uuid) && $term->parent_uuid !== null
        ? (string) $term->parent_uuid
        : '';

      if (!isset($children_by_parent[$parent_uuid])) {
        $children_by_parent[$parent_uuid] = [];
      }

      $children_by_parent[$parent_uuid][] = $term;
    }

    foreach ($children_by_parent as &$siblings) {
      usort($siblings, static function ($a, $b): int {
        $a_sort = isset($a->sort_order) ? (int) $a->sort_order : 0;
        $b_sort = isset($b->sort_order) ? (int) $b->sort_order : 0;

        if ($a_sort !== $b_sort) {
          return $a_sort <=> $b_sort;
        }

        $label_compare = strcasecmp((string) $a->label, (string) $b->label);

        if ($label_compare !== 0) {
          return $label_compare;
        }

        return strcmp((string) $a->term_uuid, (string) $b->term_uuid);
      });
    }
    unset($siblings);

    $ordered = [];

    $walk = static function (string $parent_uuid) use (&$walk, &$ordered, $children_by_parent): void {
      if (empty($children_by_parent[$parent_uuid])) {
        return;
      }

      foreach ($children_by_parent[$parent_uuid] as $term) {
        $ordered[] = $term;
        $walk((string) $term->term_uuid);
      }
    };

    $walk('');

    return $ordered;
  }

  private static function resolve_term_uuid(int $framework_id, string $term_slug_or_uuid): string
  {
    $term_slug_or_uuid = trim($term_slug_or_uuid);

    if ($term_slug_or_uuid === '') {
      return '';
    }

    if (wp_is_uuid($term_slug_or_uuid)) {
      $term = CFM_Framework_Repository::get_term_by_uuid($framework_id, $term_slug_or_uuid);
    } else {
      $term = CFM_Framework_Repository::get_term_by_slug($framework_id, sanitize_title($term_slug_or_uuid));
    }

    return $term ? (string) $term->term_uuid : '';
  }

  private static function get_profile_frameworks(): array
  {
    return array_values(array_filter(
      CFM_Framework_Repository::get_frameworks(),
      static function ($framework): bool {
        return !empty($framework->active_version_id)
          && !empty(CFM_Framework_Repository::get_compiled_terms((int) $framework->id));
      }
    ));
  }
}

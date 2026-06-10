<?php

if (!defined('ABSPATH')) {
  exit;
}

class CFM_Admin
{
  public static function init(): void
  {
    add_action('admin_menu', [__CLASS__, 'register_menu']);
    add_action('admin_init', [__CLASS__, 'handle_actions']);
  }

  public static function register_menu(): void
  {
    add_menu_page(
      'Community Frameworks',
      'Community Frameworks',
      'manage_options',
      'cfm-frameworks',
      [__CLASS__, 'render_frameworks_page'],
      'dashicons-networking',
      58
    );
  }

  public static function handle_actions(): void
  {
    if (!is_admin() || empty($_POST['cfm_action'])) {
      return;
    }

    $action = sanitize_key(wp_unslash($_POST['cfm_action']));

    if ($action === 'create_framework') {
      self::handle_create_framework();
      return;
    }

    if ($action === 'add_axis') {
      self::handle_add_axis();
      return;
    }
  }

  private static function handle_create_framework(): void
  {
    check_admin_referer('cfm_create_framework', 'cfm_nonce');

    $name = sanitize_text_field(wp_unslash($_POST['cfm_name'] ?? ''));
    $slug = sanitize_title(wp_unslash($_POST['cfm_slug'] ?? ''));
    $description = sanitize_textarea_field(wp_unslash($_POST['cfm_description'] ?? ''));

    if ($name === '' || $slug === '') {
      wp_safe_redirect(
        admin_url('admin.php?page=cfm-frameworks&cfm_error=missing_fields')
      );
      exit;
    }

    CFM_Framework_Repository::create_framework($name, $slug, $description);

    wp_safe_redirect(
      admin_url('admin.php?page=cfm-frameworks&cfm_created=1')
    );
    exit;
  }

  private static function handle_add_axis(): void
  {
    check_admin_referer('cfm_add_axis', 'cfm_nonce');

    $framework_id = absint($_POST['framework_id'] ?? 0);
    $axis_label = sanitize_text_field(wp_unslash($_POST['axis_label'] ?? ''));
    $axis_slug = sanitize_title(wp_unslash($_POST['axis_slug'] ?? ''));

    if ($framework_id <= 0 || $axis_label === '' || $axis_slug === '') {
      wp_safe_redirect(
        admin_url(
          'admin.php?page=cfm-frameworks'
            . '&action=edit'
            . '&framework_id=' . $framework_id
            . '&cfm_error=missing_axis_fields'
        )
      );
      exit;
    }

    $framework = CFM_Framework_Repository::get_framework($framework_id);

    if (!$framework) {
      wp_die('Framework not found.');
    }

    $tree = self::get_framework_tree($framework);

    if (!isset($tree['children']) || !is_array($tree['children'])) {
      $tree['children'] = [];
    }

    $tree['children'][] = [
      'uuid' => wp_generate_uuid4(),
      'label' => $axis_label,
      'slug' => $axis_slug,
      'type' => 'axis',
      'description' => '',
      'children' => [],
    ];

    CFM_Framework_Repository::create_version($framework_id, $tree, 'active');

    wp_safe_redirect(
      admin_url(
        'admin.php?page=cfm-frameworks'
          . '&action=edit'
          . '&framework_id=' . $framework_id
          . '&cfm_axis_added=1'
      )
    );
    exit;
  }

  private static function get_framework_tree(object $framework): array
  {
    $version = CFM_Framework_Repository::get_active_version((int) $framework->id);

    if ($version) {
      $tree = json_decode($version->tree_json, true);

      if (is_array($tree)) {
        return $tree;
      }
    }

    return [
      'uuid' => $framework->framework_uuid,
      'label' => $framework->name,
      'slug' => $framework->slug,
      'type' => 'framework',
      'description' => $framework->description,
      'children' => [],
    ];
  }

  public static function render_frameworks_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die('You do not have permission to access this page.');
    }

    $action = isset($_GET['action'])
      ? sanitize_key(wp_unslash($_GET['action']))
      : '';

    if ($action === 'edit') {
      self::render_framework_edit_page();
      return;
    }

    global $wpdb;

    $frameworks = $wpdb->get_results(
      "SELECT *
             FROM {$wpdb->prefix}cfm_frameworks
             ORDER BY id DESC"
    );

?>
    <div class="wrap">
      <h1>Community Frameworks</h1>

      <?php if (isset($_GET['cfm_created'])) : ?>
        <div class="notice notice-success is-dismissible">
          <p>Framework created.</p>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['cfm_error']) && $_GET['cfm_error'] === 'missing_fields') : ?>
        <div class="notice notice-error is-dismissible">
          <p>Name and slug are required.</p>
        </div>
      <?php endif; ?>

      <p>
        Create and manage reusable community classification frameworks.
        Examples: Teachers.Net profiles, Counsel.Net practice areas, BirdMart bird categories.
      </p>

      <h2>Create New Framework</h2>

      <form method="post">
        <?php wp_nonce_field('cfm_create_framework', 'cfm_nonce'); ?>

        <input type="hidden" name="cfm_action" value="create_framework">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">
              <label for="cfm_name">Name</label>
            </th>
            <td>
              <input name="cfm_name" id="cfm_name" type="text" class="regular-text" required>
              <p class="description">Example: Teachers.Net Tax Framework</p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="cfm_slug">Slug</label>
            </th>
            <td>
              <input name="cfm_slug" id="cfm_slug" type="text" class="regular-text" required>
              <p class="description">Example: teachers-net</p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="cfm_description">Description</label>
            </th>
            <td>
              <textarea name="cfm_description" id="cfm_description" class="large-text" rows="3"></textarea>
            </td>
          </tr>
        </table>

        <?php submit_button('Create Framework'); ?>
      </form>

      <hr>

      <h2>Existing Frameworks</h2>

      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Active Version</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php if (empty($frameworks)) : ?>
            <tr>
              <td colspan="7">No frameworks created yet.</td>
            </tr>
          <?php else : ?>
            <?php foreach ($frameworks as $framework) : ?>
              <tr>
                <td><?php echo esc_html($framework->id); ?></td>
                <td><?php echo esc_html($framework->name); ?></td>
                <td><code><?php echo esc_html($framework->slug); ?></code></td>
                <td><?php echo esc_html($framework->description); ?></td>
                <td><?php echo esc_html($framework->active_version_id ?: 'None'); ?></td>
                <td><?php echo esc_html($framework->created_at); ?></td>
                <td>
                  <a href="<?php echo esc_url(
                              admin_url(
                                'admin.php?page=cfm-frameworks'
                                  . '&action=edit'
                                  . '&framework_id=' . (int) $framework->id
                              )
                            ); ?>">
                    Edit
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php
  }

  public static function render_framework_edit_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die('You do not have permission to access this page.');
    }

    $framework_id = isset($_GET['framework_id'])
      ? absint($_GET['framework_id'])
      : 0;

    $framework = CFM_Framework_Repository::get_framework($framework_id);

    if (!$framework) {
      wp_die('Framework not found.');
    }

    $tree = self::get_framework_tree($framework);
    $axes = $tree['children'] ?? [];

  ?>
    <div class="wrap">
      <h1>Edit Framework: <?php echo esc_html($framework->name); ?></h1>

      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=cfm-frameworks')); ?>">
          ← Back to Community Frameworks
        </a>
      </p>

      <?php if (isset($_GET['cfm_axis_added'])) : ?>
        <div class="notice notice-success is-dismissible">
          <p>Axis added.</p>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['cfm_error']) && $_GET['cfm_error'] === 'missing_axis_fields') : ?>
        <div class="notice notice-error is-dismissible">
          <p>Axis label and slug are required.</p>
        </div>
      <?php endif; ?>

      <table class="widefat striped" style="max-width: 900px;">
        <tbody>
          <tr>
            <th style="width: 180px;">ID</th>
            <td><?php echo esc_html($framework->id); ?></td>
          </tr>
          <tr>
            <th>Name</th>
            <td><?php echo esc_html($framework->name); ?></td>
          </tr>
          <tr>
            <th>Slug</th>
            <td><code><?php echo esc_html($framework->slug); ?></code></td>
          </tr>
          <tr>
            <th>Description</th>
            <td><?php echo esc_html($framework->description); ?></td>
          </tr>
          <tr>
            <th>Active Version</th>
            <td><?php echo esc_html($framework->active_version_id ?: 'None'); ?></td>
          </tr>
        </tbody>
      </table>

      <hr>

      <h2>Existing Axes</h2>

      <?php if (empty($axes)) : ?>
        <p>No axes created yet.</p>
      <?php else : ?>
        <table class="widefat striped" style="max-width: 900px;">
          <thead>
            <tr>
              <th>Label</th>
              <th>Slug</th>
              <th>UUID</th>
              <th>Children</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($axes as $axis) : ?>
              <tr>
                <td><?php echo esc_html($axis['label'] ?? ''); ?></td>
                <td><code><?php echo esc_html($axis['slug'] ?? ''); ?></code></td>
                <td><code><?php echo esc_html($axis['uuid'] ?? ''); ?></code></td>
                <td><?php echo esc_html(count($axis['children'] ?? [])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <hr>

      <h2>Add Axis</h2>

      <form method="post">
        <?php wp_nonce_field('cfm_add_axis', 'cfm_nonce'); ?>

        <input type="hidden" name="cfm_action" value="add_axis">
        <input type="hidden" name="framework_id" value="<?php echo esc_attr($framework->id); ?>">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">
              <label for="axis_label">Axis Label</label>
            </th>
            <td>
              <input name="axis_label" id="axis_label" type="text" class="regular-text" required>
              <p class="description">Example: Grade Level, Curriculum, Region, Practice Area</p>
            </td>
          </tr>

          <tr>
            <th scope="row">
              <label for="axis_slug">Axis Slug</label>
            </th>
            <td>
              <input name="axis_slug" id="axis_slug" type="text" class="regular-text" required>
              <p class="description">Example: grade-level, curriculum, region</p>
            </td>
          </tr>
        </table>

        <?php submit_button('Add Axis'); ?>
      </form>
    </div>
<?php
  }
}

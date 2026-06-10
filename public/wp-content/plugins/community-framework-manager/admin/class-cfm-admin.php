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
    if (!is_admin()) {
      return;
    }

    if (empty($_POST['cfm_action'])) {
      return;
    }

    if ($_POST['cfm_action'] !== 'create_framework') {
      return;
    }

    if (!current_user_can('manage_options')) {
      wp_die('You do not have permission to perform this action.');
    }

    check_admin_referer('cfm_create_framework', 'cfm_nonce');

    $name = isset($_POST['cfm_name'])
      ? sanitize_text_field(wp_unslash($_POST['cfm_name']))
      : '';

    $slug = isset($_POST['cfm_slug'])
      ? sanitize_title(wp_unslash($_POST['cfm_slug']))
      : '';

    $description = isset($_POST['cfm_description'])
      ? sanitize_textarea_field(wp_unslash($_POST['cfm_description']))
      : '';

    if ($name === '' || $slug === '') {
      wp_safe_redirect(
        add_query_arg(
          ['page' => 'cfm-frameworks', 'cfm_error' => 'missing_fields'],
          admin_url('admin.php')
        )
      );
      exit;
    }

    CFM_Framework_Repository::create_framework($name, $slug, $description);

    wp_safe_redirect(
      add_query_arg(
        ['page' => 'cfm-frameworks', 'cfm_created' => '1'],
        admin_url('admin.php')
      )
    );
    exit;
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

    $table = $wpdb->prefix . 'cfm_frameworks';
    $frameworks = $wpdb->get_results(
      "SELECT id, name, slug, description, active_version_id, created_at
             FROM {$table}
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
                  <a href="<?php echo esc_url(add_query_arg(
                              [
                                'page' => 'cfm-frameworks',
                                'action' => 'edit',
                                'framework_id' => (int) $framework->id,
                              ],
                              admin_url('admin.php')
                            )); ?>">
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

    global $wpdb;

    $framework_id = isset($_GET['framework_id'])
      ? absint($_GET['framework_id'])
      : 0;

    $table = $wpdb->prefix . 'cfm_frameworks';

    $framework = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
        $framework_id
      )
    );

    if (!$framework) {
      wp_die('Framework not found.');
    }

  ?>
    <div class="wrap">
      <h1>Edit Framework</h1>

      <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=cfm-frameworks')); ?>">
          ← Back to Community Frameworks
        </a>
      </p>

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

      <h2>Framework Tree</h2>

      <p>
        This is where axes and terms will be created and arranged.
      </p>

      <p>
        Next step: add the first generic axis form.
      </p>
    </div>
<?php
  }
}

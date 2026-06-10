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
    $description = sanitize_textarea_field(
      wp_unslash($_POST['cfm_description'] ?? '')
    );

    if ($name === '' || $slug === '') {
      wp_safe_redirect(
        admin_url('admin.php?page=cfm-frameworks&cfm_error=missing_fields')
      );
      exit;
    }

    CFM_Framework_Repository::create_framework(
      $name,
      $slug,
      $description
    );

    wp_safe_redirect(
      admin_url('admin.php?page=cfm-frameworks&cfm_created=1')
    );

    exit;
  }

  private static function handle_add_axis(): void
  {
    check_admin_referer('cfm_add_axis', 'cfm_nonce');

    $framework_id = absint($_POST['framework_id'] ?? 0);

    $axis_label = sanitize_text_field(
      wp_unslash($_POST['axis_label'] ?? '')
    );

    $axis_slug = sanitize_title(
      wp_unslash($_POST['axis_slug'] ?? '')
    );

    $framework = CFM_Framework_Repository::get_framework($framework_id);

    if (!$framework) {
      wp_die('Framework not found.');
    }

    $version = CFM_Framework_Repository::get_active_version(
      $framework_id
    );

    if ($version) {
      $tree = json_decode(
        $version->tree_json,
        true
      );
    } else {
      $tree = [
        'uuid' => $framework->framework_uuid,
        'label' => $framework->name,
        'slug' => $framework->slug,
        'type' => 'framework',
        'children' => [],
      ];
    }

    if (!isset($tree['children'])) {
      $tree['children'] = [];
    }

    $tree['children'][] = [
      'uuid' => wp_generate_uuid4(),
      'label' => $axis_label,
      'slug' => $axis_slug,
      'type' => 'axis',
      'children' => [],
    ];

    CFM_Framework_Repository::create_version(
      $framework_id,
      $tree,
      'active'
    );

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

  public static function render_frameworks_page(): void
  {
    $action = sanitize_key($_GET['action'] ?? '');

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
        <div class="notice notice-success">
          <p>Framework created.</p>
        </div>
      <?php endif; ?>

      <form method="post">

        <?php wp_nonce_field(
          'cfm_create_framework',
          'cfm_nonce'
        ); ?>

        <input
          type="hidden"
          name="cfm_action"
          value="create_framework">

        <table class="form-table">

          <tr>
            <th>Name</th>
            <td>
              <input
                name="cfm_name"
                class="regular-text"
                required>
            </td>
          </tr>

          <tr>
            <th>Slug</th>
            <td>
              <input
                name="cfm_slug"
                class="regular-text"
                required>
            </td>
          </tr>

          <tr>
            <th>Description</th>
            <td>
              <textarea
                name="cfm_description"
                rows="3"
                class="large-text"></textarea>
            </td>
          </tr>

        </table>

        <?php submit_button('Create Framework'); ?>

      </form>

      <hr>

      <table class="widefat striped">

        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Version</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>

          <?php foreach ($frameworks as $framework) : ?>

            <tr>

              <td>
                <?php echo esc_html($framework->id); ?>
              </td>

              <td>
                <?php echo esc_html($framework->name); ?>
              </td>

              <td>
                <?php echo esc_html($framework->slug); ?>
              </td>

              <td>
                <?php echo esc_html(
                  $framework->active_version_id
                ); ?>
              </td>

              <td>

                <a href="<?php echo esc_url(
                            admin_url(
                              'admin.php?page=cfm-frameworks'
                                . '&action=edit'
                                . '&framework_id='
                                . $framework->id
                            )
                          ); ?>">
                  Edit
                </a>

              </td>

            </tr>

          <?php endforeach; ?>

        </tbody>

      </table>

    </div>
  <?php
  }

  public static function render_framework_edit_page(): void
  {
    $framework_id = absint(
      $_GET['framework_id'] ?? 0
    );

    $framework = CFM_Framework_Repository::get_framework(
      $framework_id
    );

  ?>
    <div class="wrap">

      <h1>
        <?php echo esc_html(
          $framework->name
        ); ?>
      </h1>

      <p>
        Add top-level axes.
      </p>

      <?php if (isset($_GET['cfm_axis_added'])) : ?>

        <div class="notice notice-success">
          <p>Axis added.</p>
        </div>

      <?php endif; ?>

      <form method="post">

        <?php wp_nonce_field(
          'cfm_add_axis',
          'cfm_nonce'
        ); ?>

        <input
          type="hidden"
          name="cfm_action"
          value="add_axis">

        <input
          type="hidden"
          name="framework_id"
          value="<?php echo esc_attr(
                    $framework->id
                  ); ?>">

        <table class="form-table">

          <tr>

            <th>Axis Label</th>

            <td>

              <input
                name="axis_label"
                required
                class="regular-text">

            </td>

          </tr>

          <tr>

            <th>Axis Slug</th>

            <td>

              <input
                name="axis_slug"
                required
                class="regular-text">

            </td>

          </tr>

        </table>

        <?php submit_button('Add Axis'); ?>

      </form>

    </div>
<?php
    return;
  }
}

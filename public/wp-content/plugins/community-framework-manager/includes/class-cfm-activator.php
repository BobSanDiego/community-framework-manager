<?php

if (!defined('ABSPATH')) {
  exit;
}

class CFM_Activator
{
  public static function activate(): void
  {
    CFM_Schema::install();

    add_option('cfm_version', CFM_VERSION);
    add_option('cfm_installed', current_time('mysql'));

    if (!get_option('cfm_seeded_teachers_net')) {
      CFM_Seeder::seed_teachers_net();
      add_option('cfm_seeded_teachers_net', current_time('mysql'));
    }
  }
}

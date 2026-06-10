<?php

if (!defined('ABSPATH')) {
  exit;
}

class CFM
{
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
}

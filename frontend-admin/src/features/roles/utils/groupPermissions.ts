import type { PermissionKey } from '@/core/permissions';

export interface PermissionGroup {
  /** The resource half of `resource.action`, e.g. `seasons`. */
  resource: string;
  permissions: PermissionKey[];
}

/**
 * Groups permissions by the resource half of their name.
 *
 * Derived, never declared. A hardcoded `const GROUPS = [...]` would mean a new
 * server resource is invisible here until someone remembers to add it, and
 * that list would be a second definition of something the names already say.
 * `broadcasts.create` appearing in the catalogue makes a Broadcasts group
 * appear, with no change to this file.
 *
 * This is also why the server sends a flat list: a permission group is a way
 * of reading a name, not an entity. Shaabjo gave groups their own table and it
 * became a dead end — the foreign key was written only by its seeder, so a
 * group created from the panel could never be populated.
 *
 * Order follows the catalogue, which is grouped already, so the UI shows
 * resources in the order the server documents them rather than alphabetically.
 */
export function groupPermissions(permissions: readonly PermissionKey[]): PermissionGroup[] {
  const groups: PermissionGroup[] = [];
  const byResource = new Map<string, PermissionGroup>();

  for (const permission of permissions) {
    const resource = permission.split('.')[0];
    let group = byResource.get(resource);

    if (group === undefined) {
      group = { resource, permissions: [] };
      byResource.set(resource, group);
      groups.push(group);
    }

    group.permissions.push(permission);
  }

  return groups;
}

/** The action half — what a checkbox is labelled with inside its group. */
export function actionOf(permission: PermissionKey): string {
  return permission.split('.')[1];
}

<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Support;

use EvolutionCMS\Models\SiteHtmlsnippet;
use EvolutionCMS\Models\SitePlugin;
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SiteTemplate;
use EvolutionCMS\Models\SiteTmplvar;
use Illuminate\Validation\ValidationException;

/**
 * What the element tools need to know about each element type: the model, the manager
 * permissions guarding it, the lock type used by Core::elementIsLocked, the columns that may be
 * read or written, and the form-save events plugins listen to.
 */
final class ElementTypes
{
    /** @var array<string, array<string, mixed>> */
    private const TYPES = [
        'template' => [
            'model' => SiteTemplate::class,
            'lock_type' => 1,
            'name_field' => 'templatename',
            'code_field' => 'content',
            'permissions' => ['view' => 'edit_template', 'new' => 'new_template', 'save' => 'save_template'],
            'events' => ['OnBeforeTempFormSave', 'OnTempFormSave'],
            'read' => ['id', 'templatename', 'templatealias', 'description', 'category', 'icon', 'template_type', 'templatefileextension', 'content', 'locked', 'selectable', 'createdon', 'editedon'],
            'write' => ['templatename', 'templatealias', 'description', 'category', 'icon', 'template_type', 'templatefileextension', 'content', 'locked', 'selectable'],
        ],
        'tv' => [
            'model' => SiteTmplvar::class,
            'lock_type' => 2,
            'name_field' => 'name',
            'code_field' => 'default_text',
            'permissions' => ['view' => 'edit_template', 'new' => 'new_template', 'save' => 'save_template'],
            'events' => ['OnBeforeTVFormSave', 'OnTVFormSave'],
            'read' => ['id', 'name', 'caption', 'description', 'type', 'category', 'elements', 'default_text', 'display', 'display_params', 'rank', 'locked', 'properties', 'createdon', 'editedon'],
            'write' => ['name', 'caption', 'description', 'type', 'category', 'elements', 'default_text', 'display', 'display_params', 'rank', 'locked'],
        ],
        'chunk' => [
            'model' => SiteHtmlsnippet::class,
            'lock_type' => 3,
            'name_field' => 'name',
            'code_field' => 'snippet',
            'permissions' => ['view' => 'edit_chunk', 'new' => 'new_chunk', 'save' => 'save_chunk'],
            'events' => ['OnBeforeChunkFormSave', 'OnChunkFormSave'],
            'read' => ['id', 'name', 'description', 'category', 'snippet', 'locked', 'disabled', 'editor_type', 'editor_name', 'cache_type', 'createdon', 'editedon'],
            'write' => ['name', 'description', 'category', 'snippet', 'locked', 'disabled', 'editor_type', 'editor_name'],
        ],
        'snippet' => [
            'model' => SiteSnippet::class,
            'lock_type' => 4,
            'name_field' => 'name',
            'code_field' => 'snippet',
            'permissions' => ['view' => 'edit_snippet', 'new' => 'new_snippet', 'save' => 'save_snippet'],
            'events' => ['OnBeforeSnipFormSave', 'OnSnipFormSave'],
            'read' => ['id', 'name', 'description', 'category', 'snippet', 'properties', 'locked', 'disabled', 'moduleguid', 'cache_type', 'createdon', 'editedon'],
            'write' => ['name', 'description', 'category', 'snippet', 'properties', 'locked', 'disabled'],
        ],
        'plugin' => [
            'model' => SitePlugin::class,
            'lock_type' => 5,
            'name_field' => 'name',
            'code_field' => 'plugincode',
            'permissions' => ['view' => 'edit_plugin', 'new' => 'new_plugin', 'save' => 'save_plugin'],
            'events' => ['OnBeforePluginFormSave', 'OnPluginFormSave'],
            'read' => ['id', 'name', 'description', 'category', 'plugincode', 'properties', 'locked', 'disabled', 'moduleguid', 'cache_type', 'createdon', 'editedon'],
            'write' => ['name', 'description', 'category', 'plugincode', 'properties', 'locked', 'disabled'],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $type): array
    {
        $type = strtolower(trim($type));
        if (!isset(self::TYPES[$type])) {
            throw ValidationException::withMessages([
                'type' => 'type must be one of: ' . implode(', ', self::names()) . '.',
            ]);
        }

        return self::TYPES[$type] + ['type' => $type];
    }
}

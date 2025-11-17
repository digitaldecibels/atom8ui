### DESCRIPTION

>This module allows fields to be edited on an entity's display (e.g. at node/123),
>not just on the node edit pages (e.g. node/123/edit).
>It also works within views, etc. Anywhere a 'formatter' can be selected, you can select editable.
>Works with ajax, so no page reloads, content is updated instantly.

### USAGE
1. Go to any place you want to use editable fields in (view mode of the entity, views);
2. Select and "Editable field" formatter;
3. Configure formatter (see CONFIGURATION section in this file);
4. The output of the formatter will be the inline form to edit field value.

### CONFIGURATION
Widget contains the next settings:
- Behaviour
- Form mode
- Bypass access check
- No access formatter
- Use fallback formatter

##### Behaviour:
Two behaviours available - inline form and popup.
Inline form - the formatter will be replaced with the form, or formatter + "Edit" button, which will show the inline form.
Popup - "Edit" button next to formatter, on click - modal window with the form will appear.

##### Form mode:
This is the only required setting.
Once you select the form mode - the edit form of the field will use the widget and settings
form the selected form mode.
`!!! Make sure the selected form mode has the field enabled and widget is configured !!!`

##### Bypass access check:
If checked - no access checks will be run. Otherwise, only users who can edit the entity will see the
edit form. If no access and bypass is not checked - there will be no form available.

##### No access formatter:
If checked - extra option "Select no access display mode" appears.
You can select a display mode, and for cases when the user has no access and "Bypass access check" is not checked
the user will see the rendered field formatter configured in the selected display mode.
`!!! Make sure the selected display mode has the field enabled and formatter is configured !!!`


##### Use fallback formatter:
This option allows to show the rendered field formatter + the "Edit" button. Once the "Edit" is clicked - formatter
is replaced with the edit form. If this option is checked - the "Select fallback display mode" setting appears.
It allows to select the display mode - the field will be rendered in the formatter form the selected display mode.
Required in case of "Popup" behaviour selected.
`!!! Make sure the selected display mode has the field enabled and formatter is configured !!!`


#### Possibility to alter field title
To alter the field title consider using the functionality provided by the
[Entity Form/Display Field Label](https://www.drupal.org/project/entity_form_field_label).

After installation, the field's title could be changed in the entity form
display setting for respected form mode.


#### Possibility to alter field help text (description)
To alter the field help text consider using the functionality
provided by the [Entity Form/Display Field Label](https://www.drupal.org/project/entity_form_field_label).
This part of the functionality is being proposed in the issue [Add possibility to alter field's help text (descriptions)](https://www.drupal.org/project/entity_form_field_label/3420361)
and by now could be implemented as a [patch](https://www.drupal.org/docs/develop/using-composer/manage-dependencies#s-patching-drupal-core-and-modules)
for the [Entity Form/Display Field Label](https://www.drupal.org/project/entity_form_field_label) module.

After installation of the module and a patch, the field's help text could be
changed in the entity form display setting for respected form mode.

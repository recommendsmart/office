# Drupal Module: Multiple Field
Data Field is a Drupal module that allows you to create custom fields with
multiple values under the default table form. This module is based on the
Triple Field and Paragraphs table modules, but it offers additional features.

**Please note**: all custom fields are not entity fields, so you cannot benefit
from field widget, field renderer, or field formatter. It's just for the data.

## How it Works
To use the Multiple Field module, follow these steps:
1. Define the storage column machine name with an unlimited value.
2. Define field column settings.
3. Define a field widget for each column.
4. Define a field display for each column.

## Supported Storage Types
Data Field supports various storage types, including:
- **Numeric**: Int, float, decimal
- **Text**: Varchar, Text, Json
- **Boolean**
- **Date iso format**: varchar (22)
- **Date mysql format**: Date, datetime, time, Year
- **Entity reference**: Taxonomy, Node, User
- **File**

## Supported Widgets
Data Field supports various widgets, including:
- **Numeric**: Textfield, range, number, select list, hidden
- **Text**: Textfield, Textarea, Text full html, Json editor, hidden
- **Boolean**: Checkbox
- **Date iso format**: Date, datetime, month, week, year
- **Date format**: Date, datetime, timestamp, time, year
- **Entity reference**: List, autocomplete, radiobutton
- **File**: File manager

## Supported Formatters
Data Field supports various formatters, including:
- Table (Bootstrap-table, datatable) with support for field permission for
creating and editing on the fly
- Chart Google chart, Hightchart
- Unformatted list order
- Details
- Known Issues
- The time of the field date (iso and unix date), which has a widget time,
needs to be checked with the time zone. It has not been tested in all cases.
- Field file may have problems when deleting old files, and an ajax call must be
sent to remove the fid in field columns.

## In Progress
Data Field is still in development, the following features are in progress:
- Edit in the field (in display mode)

 **Any help you can give will be greatly appreciated**

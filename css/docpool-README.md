# DocPool Design System

A comprehensive CSS design system for the DocPool document management interface.

## Files

- **docpool-variables.css** - CSS custom properties (variables) for theming
- **docpool.css** - Main stylesheet with all component styles

## Usage

Include both CSS files in your PHP file:

```php
$cssPath = "../css";
if (!file_exists($cssPath)) {
    if (file_exists("../../css")) $cssPath = "../../css";
    elseif (file_exists("../../../css")) $cssPath = "../../../css";
}
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/docpool-variables.css\">\n";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/docpool.css\">\n";
```

Then inject dynamic button colors as CSS variables:

```php
$lightButtonColor = brightenColor($buttonColor, 0.6);
print "<style>
    :root {
        --docpool-primary: $buttonColor;
        --docpool-primary-text: $buttonTxtColor;
        --docpool-primary-light: $lightButtonColor;
    }
</style>";
```

## Structure

### Main Components

1. **Top Bar Header** (`#topBarHeader`)
   - Navigation bar at the top
   - Back button and title

2. **Main Container** (`#docPoolContainer`)
   - Flexbox layout container
   - Fixed positioning

3. **Left Panel** (`#leftPanel`)
   - File list container
   - Fixed bottom section for uploads
   - Resizable panel

4. **Resizer** (`#resizer`)
   - Drag handle between panels
   - 5px wide divider

5. **Right Panel** (`#rightPanel`)
   - Document viewer area
   - PDF iframe container

6. **File List** (`#fileListContainer`)
   - Scrollable table of files
   - Sortable columns
   - Editable rows

7. **Fixed Bottom** (`#fixedBottom`)
   - Upload form
   - Drag & drop zone
   - Email information

## CSS Variables

All design tokens are defined in `docpool-variables.css`:

- **Colors**: Primary, secondary, text, borders, actions
- **Spacing**: xs, sm, md, lg, xl
- **Typography**: Font family, sizes, weights
- **Layout**: Panel sizes, z-index layers
- **Effects**: Shadows, transitions, border radius

## Customization

### Changing Colors

Edit `docpool-variables.css`:

```css
:root {
  --docpool-primary: #114691;
  --docpool-primary-text: #ffffff;
  --docpool-success: #28a745;
  --docpool-danger: #dc3545;
}
```

### Changing Spacing

```css
:root {
  --docpool-spacing-sm: 8px;
  --docpool-spacing-md: 12px;
  --docpool-spacing-lg: 16px;
}
```

### Dark Mode

The variables file includes a dark mode media query:

```css
@media (prefers-color-scheme: dark) {
  :root {
    --docpool-bg-main: #1a1a1a;
    --docpool-text-primary: #ffffff;
  }
}
```

## Responsive Design

The design system includes responsive breakpoints:

- **Mobile** (< 768px): Stacks panels vertically
- **Tablet/Desktop**: Side-by-side layout with resizer

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- CSS Custom Properties (CSS Variables)
- Flexbox layout
- CSS Grid (if used in future updates)

## Utility Classes

Available utility classes:

- `.docpool-text-center` - Center text
- `.docpool-text-left` - Left align text
- `.docpool-text-right` - Right align text
- `.docpool-mt-sm` - Small top margin
- `.docpool-mt-md` - Medium top margin
- `.docpool-mb-sm` - Small bottom margin
- `.docpool-mb-md` - Medium bottom margin
- `.docpool-p-sm` - Small padding
- `.docpool-p-md` - Medium padding

## Notes

- All styles use CSS custom properties for easy theming
- Inline styles in JavaScript are kept minimal (only dynamic colors)
- Print styles hide navigation and show only document viewer
- Custom scrollbar styling for better UX


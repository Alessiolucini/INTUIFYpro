# IntuiFy Landing Page

Premium landing page for IntuiFy - Web automation with AI assistants.

## Requirements

- PHP 8.0+
- Apache with mod_rewrite (or compatible web server)

## Quick Start

```bash
# Start local development server
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

## Features

- **Multilingual**: Auto-detects ES/IT/EN from browser, switch via `?lang=es|it|en`
- **Responsive**: Mobile-first design with hamburger menu
- **Accessible**: Focus rings, aria-labels, proper contrast
- **Performance**: Tailwind via CDN, minimal JS, no dependencies
- **Anti-spam**: Honeypot, time-trap (3s), session rate limiting

## File Structure

```
├── index.php          # Main entry point + form handling
├── includes/
│   ├── header.php     # Fixed header + nav
│   └── footer.php     # Footer
├── assets/
│   └── logo.png       # Brand logo (replace with actual)
├── i18n/
│   ├── es.json        # Spanish
│   ├── it.json        # Italian
│   └── en.json        # English
├── .htaccess          # Security headers + rewrite
└── README.md          # This file
```

## Contact Form

Submits via AJAX POST to n8n webhook:
`https://intuifypersonale-n8n.oqlfv4.easypanel.host/webhook-test/73129db0-a899-412d-b0f3-0a32fac8b692`

Payload:
```json
{
  "nombre_completo": "...",
  "empresa": "...",
  "email": "...",
  "mensaje": "...",
  "lang": "es|it|en",
  "timestamp": "ISO 8601"
}
```

## Customization

### Colors
Edit CSS variables in `index.php`:
```css
:root {
  --primary:    #0F172A;
  --accent:     #6366F1;
  --secondary:  #8B5CF6;
  --text-light: #F8FAFC;
  --text-dark:  #1E293B;
  --bg-light:   #F8FAFC;
  --bg-dark:    #0F172A;
}
```

### Logo
Replace `assets/logo.png` with your actual logo (recommended: 200x64px PNG with transparency).

---

© 2026 IntuiFy

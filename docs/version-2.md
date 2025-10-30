# Flux Icons

This is a laravel package to customize the icons for [Livewire Flux](https://github.com/livewire/flux). It builds the icons from various vendors into a `flux:icon` component.

[![Packagist Version](https://img.shields.io/packagist/v/ympact/flux-icons)](https://packagist.org/packages/ympact/flux-icons)

## Roadmap v2

- [x] Support for Blaze
- [ ] More customization properties on individual icons
- [ ] Moving from config array to vendor classes
- [ ] Add/Improve command for updating/rebuilding icons
- [ ] Adding more vendors
- [ ] Helper script to create configurations for new vendors
- [ ] Improving testing
- [ ] Documentation
- [ ] Support other variants (duotone, etc.)
- [ ] Creating custom icons (ie combining icons)
- [ ] Simpler artisan commands (default vendor can be omitted)
- [ ] Defining a baseline on images for improved inline placement.




<!-- CSS approach: treat svg as inline and nudge its baseline -->
<style>
.icon { 
  width: 1em; height: 1em; 
  vertical-align: -0.125em; /* move graphic up relative to text baseline */
  /* or vertical-align: middle | text-bottom | baseline */
}
</style>

<span>Text <svg class="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
  <!-- draw centered so vertical-align behaves predictably -->
  <g transform="translate(0,0)"><path d="..."/></g>
</svg> more text</span>


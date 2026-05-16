---
must_haves:
  truths:
    - Application is running on Livewire v4 with full compatibility.
    - Frontend is successfully built using Tailwind CSS v4 and Mary UI v2.
  artifacts:
    - updated: composer.json
    - updated: package.json
    - updated: resources/css/app.css
  key_links:
    - from: resources/css/app.css
      to: public/build/assets/
      via: npm run build
requirements:
  - R1.1
  - R1.2
depends_on: []
---

# Plan: Phase 1.1 - Modernization Upgrade

Upgrade the core application stack to Livewire v4 and Tailwind CSS v4.

<task id="dependency_update" requirement="R1.1, R1.2">
  <files>
    <file>composer.json</file>
    <file>package.json</file>
  </files>
  <action>
    Update composer.json to require `livewire/livewire:^4.0`, `livewire/volt:^1.7`, and `mary-ui/mary-ui:^2.0`. Update package.json to require `tailwindcss:^4.0` and `daisyui:^5.0`.
  </action>
  <verify>
    <automated>composer update && npm install && vendor/bin/pint --dirty && php artisan test --filter=ExampleTest</automated>
  </verify>
  <done>
    Dependencies are updated and locked.
  </done>
</task>

<task id="livewire_migration" requirement="R1.1">
  <files>
    <file>config/livewire.php</file>
  </files>
  <action>
    Run `php artisan livewire:upgrade --no-interaction`. Follow migration steps for configuration changes.
  </action>
  <verify>
    <automated>php artisan test --filter=AdminRuleAndRegulationIndexTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Livewire v4 upgrade script completed.
  </done>
</task>

<task id="tailwind_migration" requirement="R1.2">
  <files>
    <file>resources/css/app.css</file>
    <file>tailwind.config.js</file>
  </files>
  <action>
    Run `npx @tailwindcss/upgrade`. Remove `tailwind.config.js` and `postcss.config.js` if successfully migrated to CSS-first config.
  </action>
  <verify>
    <automated>npm run build && php artisan test --filter=ResponsiveDesignTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Tailwind CSS v4 migration completed.
  </done>
</task>

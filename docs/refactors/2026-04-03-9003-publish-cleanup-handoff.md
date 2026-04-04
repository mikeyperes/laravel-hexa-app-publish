# 9003 Publish Cleanup Handoff

Date: 2026-04-03
Project: `laravel-hexa-app-publish`
Local app: `http://127.0.0.1:9003`
Scope: non-breaking architectural cleanup and sectionalization

## Goal

Clean the package properly without breaking the live app surface.

This is not a rename-everything exercise. The target is:

- clearer broader service roots
- real domain ownership
- shared workflow extraction
- thin controllers
- stable route names and URLs during migration

## Current Verified State

Verified hotspots:

- [`src/Http/Controllers/PublishPipelineController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishPipelineController.php) `1154` lines
- [`src/Http/Controllers/PublishArticleController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishArticleController.php) `780` lines
- [`src/Campaigns/Services/CampaignRunService.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Services/CampaignRunService.php) `359` lines
- [`routes/app-publish.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/routes/app-publish.php) is a single monolithic route file
- [`src/Services/PublishService.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Services/PublishService.php) is only a version stub, not a real application service
- models are mixed between [`src/Models`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Models) and [`src/Campaigns/Models`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Models)
- services are mixed between [`src/Services`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Services) and [`src/Campaigns/Services`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Services)
- views are inconsistent across [`resources/views/article`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/article), [`resources/views/articles`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/articles), [`resources/views/publishing`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/publishing), [`resources/views/campaigns`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/campaigns), [`resources/views/search`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/search), [`resources/views/sites`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/sites)

## Non-Breaking Rules

Claude must not violate these during cleanup:

1. Do not rename existing route names in phase 1.
Keep:

- `publish.*`
- `campaigns.*`

2. Do not change existing URLs in phase 1.
Keep paths like:

- `/article/publish`
- `/publish/articles`
- `/campaigns`
- `/publish/sites`

3. Do not rewrite existing migration history.
There are `31` migrations already. Leave them intact during structural refactor.

4. Do not turn `PublishService` into a god service.
Create explicit new services instead.

5. Do not move external integration logic into random controllers.
All provider-specific transport should live behind dedicated services.

6. Do not delete legacy wrapper classes until references are migrated and tests pass.

7. Do not mix route refactor and feature refactor in the same step unless the route names remain identical.

## Namespace Strategy

Stay inside the existing package root namespace:

- `hexa_app_publish\...`

Add broader service roots under that namespace:

- `hexa_app_publish\Publishing\...`
- `hexa_app_publish\Discovery\...`
- `hexa_app_publish\Quality\...`
- `hexa_app_publish\Shared\...`

Do not create new Composer PSR-4 roots yet.

## Target Folder Tree

```text
src/
  Publishing/
    Accounts/
      Http/Controllers/
      Models/
      Actions/
      Services/
    Articles/
      Http/Controllers/
      Models/
      Actions/
      Services/
    Pipeline/
      Http/Controllers/
      Actions/
      Services/
    Campaigns/
      Http/Controllers/
      Models/
      Actions/
      Services/
    Sites/
      Http/Controllers/
      Models/
      Actions/
      Services/
    Delivery/
      Services/
      DTOs/
    Templates/
      Http/Controllers/
      Models/
    Presets/
      Http/Controllers/
      Models/
    Prompts/
      Http/Controllers/
      Models/
    Schedule/
      Http/Controllers/
      Services/
    Dashboard/
      Http/Controllers/
  Discovery/
    Search/
      Http/Controllers/
      Services/
    Sources/
      Services/
      DTOs/
    Media/
      Services/
    Links/
      Http/Controllers/
      Models/
      Services/
  Quality/
    Detection/
      Http/Controllers/
      Models/
      Services/
    SmartEdits/
      Http/Controllers/
      Models/
  Shared/
    Contracts/
    DTOs/
    Support/
    ViewModels/
```

## New Shared Services To Create

Create these first. They are the cleanup backbone.

- `hexa_app_publish\Publishing\Services\PublishWorkflowService`
- `hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService`
- `hexa_app_publish\Discovery\Sources\Services\SourceExtractionService`
- `hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService`
- `hexa_app_publish\Publishing\Articles\Services\MetadataGenerationService`
- `hexa_app_publish\Publishing\Delivery\Services\WordPressConnectionService`
- `hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService`
- `hexa_app_publish\Publishing\Delivery\Services\WordPressDeletionService`
- `hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService`
- `hexa_app_publish\Publishing\Services\RunLogService`

## Exact File Move Map

These are the target destinations. Keep old classes as wrappers during transition where necessary.

### Controllers

- `src/Http/Controllers/PublishAccountController.php`
  -> `src/Publishing/Accounts/Http/Controllers/AccountController.php`

- `src/Http/Controllers/PublishArticleController.php`
  -> `src/Publishing/Articles/Http/Controllers/ArticleController.php`

- `src/Http/Controllers/PublishBookmarkController.php`
  -> `src/Publishing/Articles/Http/Controllers/BookmarkController.php`

- `src/Http/Controllers/PublishDashboardController.php`
  -> `src/Publishing/Dashboard/Http/Controllers/DashboardController.php`

- `src/Http/Controllers/PublishDraftController.php`
  -> `src/Publishing/Articles/Http/Controllers/DraftController.php`

- `src/Http/Controllers/PublishLinkController.php`
  -> `src/Discovery/Links/Http/Controllers/LinkController.php`

- `src/Http/Controllers/PublishMasterSettingController.php`
  -> `src/Publishing/Settings/Http/Controllers/MasterSettingController.php`

- `src/Http/Controllers/PublishPipelineController.php`
  -> `src/Publishing/Pipeline/Http/Controllers/PipelineController.php`

- `src/Http/Controllers/PublishPresetController.php`
  -> `src/Publishing/Presets/Http/Controllers/PresetController.php`

- `src/Http/Controllers/PublishPromptController.php`
  -> `src/Publishing/Prompts/Http/Controllers/PromptController.php`

- `src/Http/Controllers/PublishScheduleController.php`
  -> `src/Publishing/Schedule/Http/Controllers/ScheduleController.php`

- `src/Http/Controllers/PublishSearchController.php`
  -> `src/Discovery/Search/Http/Controllers/SearchController.php`

- `src/Http/Controllers/PublishSettingsController.php`
  -> `src/Publishing/Settings/Http/Controllers/SettingsController.php`

- `src/Http/Controllers/PublishSiteController.php`
  -> `src/Publishing/Sites/Http/Controllers/SiteController.php`

- `src/Http/Controllers/PublishTemplateController.php`
  -> `src/Publishing/Templates/Http/Controllers/TemplateController.php`

- `src/Http/Controllers/AiActivityController.php`
  -> `src/Quality/Detection/Http/Controllers/AiActivityController.php`

- `src/Http/Controllers/AiSmartEditController.php`
  -> `src/Quality/SmartEdits/Http/Controllers/SmartEditController.php`

- `src/Campaigns/Http/Controllers/CampaignController.php`
  -> `src/Publishing/Campaigns/Http/Controllers/CampaignController.php`

- `src/Campaigns/Http/Controllers/CampaignPresetController.php`
  -> `src/Publishing/Campaigns/Http/Controllers/CampaignPresetController.php`

### Models

- `src/Models/PublishAccount.php`
  -> `src/Publishing/Accounts/Models/PublishAccount.php`

- `src/Models/PublishAccountUser.php`
  -> `src/Publishing/Accounts/Models/PublishAccountUser.php`

- `src/Models/PublishArticle.php`
  -> `src/Publishing/Articles/Models/PublishArticle.php`

- `src/Models/PublishBookmark.php`
  -> `src/Publishing/Articles/Models/PublishBookmark.php`

- `src/Models/PublishCampaign.php`
  -> `src/Publishing/Campaigns/Models/PublishCampaign.php`

- `src/Campaigns/Models/CampaignPreset.php`
  -> `src/Publishing/Campaigns/Models/CampaignPreset.php`

- `src/Models/PublishLinkList.php`
  -> `src/Discovery/Links/Models/PublishLinkList.php`

- `src/Models/PublishSitemap.php`
  -> `src/Discovery/Links/Models/PublishSitemap.php`

- `src/Models/PublishUsedSource.php`
  -> `src/Discovery/Sources/Models/PublishUsedSource.php`

- `src/Models/PublishMasterSetting.php`
  -> `src/Publishing/Settings/Models/PublishMasterSetting.php`

- `src/Models/PublishPreset.php`
  -> `src/Publishing/Presets/Models/PublishPreset.php`

- `src/Models/PublishPrompt.php`
  -> `src/Publishing/Prompts/Models/PublishPrompt.php`

- `src/Models/PublishSite.php`
  -> `src/Publishing/Sites/Models/PublishSite.php`

- `src/Models/PublishTemplate.php`
  -> `src/Publishing/Templates/Models/PublishTemplate.php`

- `src/Models/AiActivityLog.php`
  -> `src/Quality/Detection/Models/AiActivityLog.php`

- `src/Models/AiDetectionLog.php`
  -> `src/Quality/Detection/Models/AiDetectionLog.php`

- `src/Models/AiSmartEditTemplate.php`
  -> `src/Quality/SmartEdits/Models/AiSmartEditTemplate.php`

### Services

- `src/Services/PublishService.php`
  -> keep in place temporarily as compatibility stub

- `src/Services/ArticleDeleteService.php`
  -> `src/Publishing/Delivery/Services/WordPressDeletionService.php`

- `src/Services/LinkInsertionService.php`
  -> `src/Discovery/Links/Services/LinkInsertionService.php`

- `src/Campaigns/Services/CampaignRunService.php`
  -> `src/Publishing/Campaigns/Services/CampaignExecutionService.php`

### Console

- `src/Console/RunCampaignsCommand.php`
  -> keep path for now or move to `src/Publishing/Campaigns/Console/RunCampaignsCommand.php` only after command registration is updated safely

## Legacy Wrapper Rule

For moved PHP classes, use wrappers during migration.

Example pattern:

- old class path stays
- old class extends new class
- old namespace remains temporarily valid

This avoids breaking:

- route action resolution
- DI bindings
- imports across views/controllers/services

Do not remove wrappers until:

- all internal imports are updated
- route:list passes
- smoke tests pass

## Route File Split Plan

Split [`routes/app-publish.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/routes/app-publish.php) into:

- `routes/publishing/accounts.php`
- `routes/publishing/sites.php`
- `routes/publishing/articles.php`
- `routes/publishing/pipeline.php`
- `routes/publishing/campaigns.php`
- `routes/publishing/templates.php`
- `routes/publishing/presets.php`
- `routes/publishing/prompts.php`
- `routes/publishing/settings.php`
- `routes/publishing/schedule.php`
- `routes/discovery/search.php`
- `routes/discovery/links.php`
- `routes/quality/detection.php`

Rules:

- keep exact route names
- keep exact URLs
- keep current middleware stack
- move only file location and registration, not route semantics

## View Restructure Plan

Do not start here. Do this after controllers are moved and wrappers exist.

Target view roots:

- `resources/views/publishing/accounts`
- `resources/views/publishing/articles`
- `resources/views/publishing/pipeline`
- `resources/views/publishing/campaigns`
- `resources/views/publishing/sites`
- `resources/views/publishing/templates`
- `resources/views/publishing/presets`
- `resources/views/publishing/prompts`
- `resources/views/publishing/settings`
- `resources/views/publishing/schedule`
- `resources/views/discovery/search`
- `resources/views/discovery/links`
- `resources/views/quality/detection`
- `resources/views/quality/smart-edits`

Current mixed folders to normalize later:

- `article/`
- `articles/`
- `publishing/`
- `campaigns/`
- `search/`
- `links/`
- `sites/`
- `templates/`
- `accounts/`
- `ai-activity/`
- `smart-edits/`

## Workflow Extraction Order

Do this before splitting controllers aggressively.

### Step 1

Extract `SourceDiscoveryService`

Move into one place:

- search provider selection
- Currents / GNews / NewsData logic
- source URL normalization

Current duplication exists across:

- [`PublishArticleController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishArticleController.php)
- [`PublishSearchController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishSearchController.php)
- [`CampaignRunService.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Services/CampaignRunService.php)

### Step 2

Extract `SourceExtractionService`

Move into one place:

- URL scrape/extract
- extractor result normalization

### Step 3

Extract `ArticleGenerationService`

Move into one place:

- Anthropic/AI prompt submission
- spin response normalization
- cost/token extraction

### Step 4

Extract `MetadataGenerationService`

Move into one place:

- title/description/category/tag generation
- AI detection threshold handling

### Step 5

Extract `WordPressDeliveryService`

Move into one place:

- REST create
- WP Toolkit create
- status normalization
- URL normalization
- scheduled publish handling

### Step 6

Extract `WordPressDeletionService`

Move into one place:

- REST delete
- WP Toolkit delete
- media delete
- result normalization

### Step 7

Extract `PublishWorkflowService`

This orchestrates:

- discover
- extract
- generate
- metadata
- prepare
- persist
- deliver

This service should be used by:

- pipeline
- article flows where relevant
- campaign execution

## Controller Cleanup Order

### Phase A

Clean [`PublishPipelineController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishPipelineController.php)

Move logic out for:

- source check
- spin
- metadata generation
- AI detection
- prepare
- publish
- save draft

### Phase B

Clean [`PublishArticleController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Http/Controllers/PublishArticleController.php)

Split by responsibility:

- article CRUD stays in `Articles`
- source search/scrape moves to `Discovery`
- photo search moves to `Discovery/Media`
- AI/SEO checks move to `Quality`
- link insertion moves to `Discovery/Links`
- publish action delegates to `WordPressDeliveryService`

### Phase C

Clean [`CampaignRunService.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Services/CampaignRunService.php)

Make it a thin campaign orchestrator on top of `PublishWorkflowService`.

### Phase D

Clean [`CampaignController.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/src/Campaigns/Http/Controllers/CampaignController.php)

Keep it as:

- CRUD
- run-now endpoint
- page rendering

Push workflow/business logic down.

## Frontend Cleanup Order

Priority duplication to remove:

- site test / author loading in campaign create
- site test / author loading in pipeline
- duplicated connection cache handling
- duplicated activity/debug state logic

Create one shared JS module for:

- connection test
- author loading
- cached site state
- status/message normalization

Do not leave this logic embedded separately in:

- [`resources/views/campaigns/create.blade.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/campaigns/create.blade.php)
- [`resources/views/article/pipeline/index.blade.php`](/Users/mp/Projects/smp-publish/packages/hexawebsystems/laravel-hexa-app-publish/resources/views/article/pipeline/index.blade.php)

## Migrations Rule

Do not consolidate or rewrite migrations during the main refactor.

Current rule:

- keep all `31` migrations
- add new migrations only if schema truly changes
- do not squash until the code structure is stable

Optional later step:

- create a fresh-install baseline migration set after architecture is stable
- keep legacy migrations for upgrade paths

## Testing Gates

After every phase, Claude must prove:

1. `php artisan route:list` passes
2. `php -l` passes on changed PHP files
3. `/article/publish?id=33` loads
4. `/campaigns/2` loads
5. dashboard loads
6. site show page loads
7. campaign create does not auto-fire site test on load
8. campaign links resolve with `campaigns.*`
9. one draft article save works
10. one campaign run-now request reaches the backend without route failure

If a phase touches WordPress delivery or delete:

11. SSH path syntax verified
12. REST path syntax verified

Do not claim a live WordPress publish/delete pass without showing the actual target site and result.

## What Claude Must Not Do

- Do not rename `campaigns.*` to `publish.campaigns.*`
- Do not rename `publish.*` routes in the cleanup pass
- Do not merge everything into `PublishService`
- Do not move migrations around just to make folders look tidy
- Do not delete wrappers early
- Do not mix route renames with namespace moves
- Do not add more duplicate connection logic
- Do not put REST transport calls in controllers

## First Commit Sequence

Use this exact order:

1. add new target folders
2. create new shared service classes
3. move one workflow slice at a time behind wrappers
4. move pipeline controller internals to services
5. move article controller internals to services
6. move campaign execution to shared workflow
7. split route files while preserving names
8. normalize views
9. remove wrappers only after full pass

## Definition Of Done

The cleanup is done when:

- no fat global `src/Http/Controllers` remains for publishing domains
- no fat global `src/Models` remains for publishing domains
- `Campaigns` lives under `Publishing`
- pipeline and campaigns share one workflow backbone
- source discovery exists once
- WordPress delivery/delete exists once
- route names and URLs remain stable
- frontend site-test logic exists once
- views mirror the same service/domain structure


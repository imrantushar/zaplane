# Contributing Guide

Thank you for considering contributing to Zaplane! This guide will help you get started.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)

---

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all. Please be respectful and constructive in all interactions.

### Expected Behavior

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Trolling, insulting/derogatory comments, and personal attacks
- Public or private harassment
- Publishing others' private information without permission
- Other conduct which could reasonably be considered inappropriate

---

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- WordPress 6.0 or higher
- Composer (for development dependencies)
- Node.js & npm (for building frontend assets)
- Git

### Development Setup

1. **Clone the repository**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/yourusername/zaplane.git
   cd zaplane
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Build assets**
   ```bash
   npm run build
   ```

4. **Run migrations**
   ```bash
   wp zaplane migrate
   ```

5. **Activate plugin**
   ```bash
   wp plugin activate zaplane
   ```

### Development Environment

We recommend using Local by Flywheel or similar for WordPress development:

```bash
# Using Local WP
local start

# Or using wp-env
npm -g install @wordpress/env
wp-env start
```

---

## Development Workflow

### 1. Create a Branch

Always create a new branch for your work:

```bash
# Feature branch
git checkout -b feature/your-feature-name

# Bug fix branch
git checkout -b fix/issue-description

# Documentation
git checkout -b docs/what-you-are-documenting
```

### 2. Make Changes

- Write clean, readable code
- Follow existing code style
- Add comments for complex logic
- Update documentation as needed

### 3. Test Your Changes

```bash
# Run PHP tests
composer test

# Run specific test
composer test -- --filter=TestClassName

# Check code style
composer phpcs

# Fix code style
composer phpcbf

# Build assets
npm run build

# Watch for changes
npm run watch
```

### 4. Commit Changes

Write clear, descriptive commit messages:

```bash
# Good commit messages
git commit -m "Add user authentication to workflows"
git commit -m "Fix query caching bug in QueryBuilder"
git commit -m "Update installation documentation"

# Follow conventional commits format
git commit -m "feat: add OAuth2 authentication"
git commit -m "fix: resolve memory leak in integration loader"
git commit -m "docs: update API reference for models"
```

**Commit Message Format:**
```
<type>: <description>

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### 5. Push Changes

```bash
git push origin your-branch-name
```

### 6. Create Pull Request

- Go to GitHub and create a pull request
- Fill out the PR template completely
- Link related issues
- Request review from maintainers

---

## Coding Standards

### PHP Code Style

We follow **PSR-12** coding standards with some WordPress conventions:

```php
<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

class Workflow extends Model
{
    // Use 4 spaces for indentation
    protected static string $table = 'workflows';

    // Opening brace on same line for methods
    public function getTitle(): string {
        return $this->title;
    }

    // Type hints for all parameters and return values
    public function createVersion(array $graph): WorkflowVersion {
        return WorkflowVersion::create([
            'workflow_id' => $this->id,
            'graph_json' => json_encode($graph),
            'graph_hash' => hash('sha256', json_encode($graph)),
            'is_active' => true
        ]);
    }
}
```

**Key Points:**
- Always use type hints
- Document complex methods with PHPDoc
- Use meaningful variable names
- Keep methods focused and small
- Follow WordPress naming for hooks and filters

### JavaScript Code Style

```javascript
// Use const/let, never var
const workflowId = 123;
let status = 'active';

// Use arrow functions
const getWorkflows = () => {
    return fetch('/wp-json/zaplane/v1/workflows')
        .then(response => response.json());
};

// Async/await for promises
async function loadWorkflow(id) {
    try {
        const response = await fetch(`/wp-json/zaplane/v1/workflows/${id}`);
        const workflow = await response.json();
        return workflow;
    } catch (error) {
        console.error('Failed to load workflow:', error);
    }
}
```

### CSS/SCSS Style

```scss
// Use BEM naming
.zaplane-workflow {
    padding: 20px;

    &__header {
        font-size: 24px;
        font-weight: bold;
    }

    &__content {
        margin-top: 16px;
    }

    &--active {
        border-left: 4px solid #00a32a;
    }
}

// Use variables
$primary-color: #2271b1;
$spacing-unit: 8px;

.zaplane-button {
    background: $primary-color;
    padding: $spacing-unit * 2;
}
```

### Documentation Standards

```php
/**
 * Create a new workflow version with the given graph
 *
 * This method handles:
 * - JSON encoding of the graph
 * - SHA-256 hash generation for content-addressable storage
 * - Deactivating previous active versions
 * - Creating the new version record
 *
 * @param array $graph Workflow graph with nodes and edges
 * @return WorkflowVersion The created workflow version
 * @throws DatabaseException If version creation fails
 *
 * @example
 * $version = $workflow->createVersion([
 *     'nodes' => [...],
 *     'edges' => [...]
 * ]);
 */
public function createVersion(array $graph): WorkflowVersion {
    // Implementation...
}
```

---

## Testing

### Writing Tests

All new features should include tests:

```php
<?php

namespace Zaplane\Tests;

use PHPUnit\Framework\TestCase;
use Zaplane\Models\Workflow;

class WorkflowTest extends TestCase
{
    public function test_create_workflow()
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'title' => 'Test Workflow',
            'name' => 'test-workflow',
            'status' => 'draft'
        ]);

        $this->assertNotNull($workflow->id);
        $this->assertEquals('Test Workflow', $workflow->title);
        $this->assertEquals('draft', $workflow->status);
    }

    public function test_workflow_can_have_versions()
    {
        $workflow = Workflow::create([
            'user_id' => 1,
            'title' => 'Test',
            'name' => 'test',
            'status' => 'draft'
        ]);

        $version = $workflow->createVersion([
            'nodes' => [],
            'edges' => []
        ]);

        $this->assertNotNull($version->id);
        $this->assertEquals($workflow->id, $version->workflow_id);
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
composer test tests/Models/WorkflowTest.php

# Run with coverage
composer test -- --coverage-html coverage/
```

### Integration Tests

```php
public function test_workflow_execution()
{
    // Create workflow
    $workflow = Workflow::create([...]);

    // Create version
    $version = WorkflowVersion::create([...]);

    // Execute workflow
    $run = Run::create([
        'workflow_version_hash' => $version->graph_hash,
        'status' => 'running'
    ]);

    // Assert execution
    $this->assertEquals('running', $run->status);
}
```

---

## Pull Request Process

### Before Submitting

**Checklist:**
- [ ] Code follows style guidelines
- [ ] All tests pass
- [ ] New tests added for new features
- [ ] Documentation updated
- [ ] Commit messages are clear
- [ ] No merge conflicts
- [ ] Branch is up to date with master

### PR Template

When creating a PR, please include:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How was this tested?

## Screenshots
If applicable, add screenshots

## Checklist
- [ ] Code follows style guidelines
- [ ] Tests added/updated
- [ ] Documentation updated
```

### Review Process

1. **Automated Checks** - CI/CD runs tests and linters
2. **Code Review** - Maintainer reviews code
3. **Changes Requested** - Address feedback
4. **Approval** - PR is approved
5. **Merge** - Maintainer merges PR

### After Merge

- PR is squashed and merged to master
- Branch is automatically deleted
- Changelog is updated
- Release notes prepared

---

## Issue Reporting

### Before Creating an Issue

1. **Search existing issues** - Your issue may already exist
2. **Check documentation** - Solution might be documented
3. **Try latest version** - Bug might be fixed

### Bug Reports

Use the bug report template:

```markdown
## Bug Description
Clear description of the bug

## Steps to Reproduce
1. Go to '...'
2. Click on '...'
3. See error

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- WordPress version:
- PHP version:
- Zaplane version:
- Browser (if applicable):

## Screenshots
If applicable

## Additional Context
Any other relevant information
```

### Feature Requests

```markdown
## Feature Description
Clear description of the feature

## Use Case
Why is this feature needed?

## Proposed Solution
How should this work?

## Alternatives Considered
Other approaches you've thought about

## Additional Context
Mockups, examples, etc.
```

---

## Architecture Guidelines

### Adding New Models

1. Create migration in `includes/migrations/`
2. Create model in `includes/models/`
3. Add relationships to related models
4. Write tests
5. Update documentation

Example:
```php
// Migration
class CreateTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function(Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}

// Model
class Task extends Model
{
    protected static string $table = 'tasks';
    protected static array $fillable = ['title', 'description'];
}
```

### Adding New Integrations

1. Create integration class in `includes/integrations/`
2. Extend `IntegrationBase`
3. Implement required methods
4. Add icon and metadata
5. Write tests
6. Add documentation

See [Creating Integrations Guide](../guides/creating-integrations.md)

### Adding API Endpoints

1. Create controller in `includes/api/controllers/`
2. Register routes in `includes/api/routes.php`
3. Add permission checks
4. Validate input
5. Write tests
6. Document endpoint

---

## Community

### Getting Help

- **Documentation:** https://github.com/yourusername/zaplane/tree/master/docs
- **Discussions:** https://github.com/yourusername/zaplane/discussions
- **Issues:** https://github.com/yourusername/zaplane/issues
- **Discord:** Join our community server

### Recognition

Contributors are recognized in:
- GitHub contributors page
- Release notes
- Project README
- Annual contributor spotlight

---

## License

By contributing to Zaplane, you agree that your contributions will be licensed under the same license as the project (GPL-2.0+).

---

## Questions?

If you have any questions about contributing:

1. Check the [FAQ](../getting-started/faq.md)
2. Search [Discussions](https://github.com/yourusername/zaplane/discussions)
3. Open a new discussion
4. Contact maintainers

---

**Thank you for contributing to Zaplane!** 🎉

Every contribution, no matter how small, makes a difference.

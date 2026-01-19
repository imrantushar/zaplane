# Zaplane Documentation

<p align="center">
  <img src="https://img.shields.io/badge/version-0.0.1-blue.svg" alt="Version">
  <img src="https://img.shields.io/badge/WordPress-6.0+-green.svg" alt="WordPress">
  <img src="https://img.shields.io/badge/PHP-8.0+-purple.svg" alt="PHP">
  <img src="https://img.shields.io/badge/tests-302%20passing-brightgreen.svg" alt="Tests">
  <img src="https://img.shields.io/badge/performance-3X%20faster-orange.svg" alt="Performance">
</p>

Welcome to the Zaplane documentation! This guide will help you understand, develop, and extend the Zaplane WordPress automation plugin.

---

## 📚 Table of Contents

### Getting Started
- [**Quick Start**](getting-started/quick-start.md) - Get up and running in 5 minutes
- [**Installation**](getting-started/installation.md) - Detailed installation guide
- [**Configuration**](getting-started/configuration.md) - Plugin configuration
- [**First Workflow**](getting-started/first-workflow.md) - Create your first automation

### Architecture
- [**Overview**](architecture/overview.md) - High-level system architecture
- [**Database Schema**](architecture/database.md) - Database tables and relationships
- [**ORM System**](architecture/orm.md) - Custom ORM documentation
- [**Config System**](architecture/config.md) - Configuration management
- [**Logging System**](architecture/logging.md) - Logging framework
- [**Integration System**](architecture/integrations.md) - How integrations work
- [**Workflow Engine**](architecture/workflow-engine.md) - Automation execution flow

### API Reference
- [**Models**](api/models.md) - Database models API
- [**Query Builder**](api/query-builder.md) - ORM query builder
- [**Collections**](api/collections.md) - Collection methods
- [**Config**](api/config.md) - Configuration API
- [**Logger**](api/logger.md) - Logging API
- [**Integration Base**](api/integration-base.md) - Creating integrations

### Developer Guides
- [**Creating Integrations**](guides/creating-integrations.md) - Build custom integrations
- [**Working with Models**](guides/working-with-models.md) - Database operations
- [**Writing Tests**](guides/writing-tests.md) - Testing guide
- [**Migrations**](guides/migrations.md) - Database migrations
- [**Debugging**](guides/debugging.md) - Debugging techniques
- [**Best Practices**](guides/best-practices.md) - Code standards

### Performance
- [**Performance Report**](performance/comparison.md) - next-release vs refactor-structure
- [**Optimization Guide**](performance/optimization.md) - Performance tips
- [**Benchmarks**](performance/benchmarks.md) - Performance metrics
- [**Caching Strategy**](performance/caching.md) - Query and object caching

### Contributing
- [**Contributing Guide**](contributing/guide.md) - How to contribute
- [**Code Standards**](contributing/code-standards.md) - Coding conventions
- [**Pull Request Template**](contributing/pull-request.md) - PR guidelines
- [**Testing Guidelines**](contributing/testing.md) - Test requirements

---

## 🚀 Quick Links

- **Installation**: [Get started in 5 minutes](getting-started/quick-start.md)
- **Architecture**: [Understand the system](architecture/overview.md)
- **API Docs**: [Complete API reference](api/models.md)
- **Performance**: [3X faster than previous version](performance/comparison.md)

---

## 📖 What is Zaplane?

Zaplane is a powerful WordPress automation plugin that enables you to create n8n-style workflow automations directly within WordPress. It features:

- **🎯 Visual Workflow Builder** - Create automations with a drag-and-drop interface
- **🔌 Extensible Integrations** - Connect with external services (Slack, Stripe, etc.)
- **⚡ High Performance** - 3X faster than previous architecture
- **🧪 Fully Tested** - 302 automated tests ensure reliability
- **🏗️ Modern Architecture** - Laravel-inspired ORM, Config, and Logging systems
- **📦 Easy to Extend** - Clean API for custom integrations

---

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Zaplane Plugin                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │   React UI   │  │  REST API    │  │  Automation  │ │
│  │  (Frontend)  │  │ (Controllers)│  │    Engine    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │              Framework Layer                      │  │
│  │  ┌──────┐  ┌──────┐  ┌─────┐  ┌──────────────┐ │  │
│  │  │ ORM  │  │Config│  │ Log │  │ Integrations │ │  │
│  │  └──────┘  └──────┘  └─────┘  └──────────────┘ │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │               Data Layer                          │  │
│  │   Models │ Query Builder │ Collections │ Schema  │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │           WordPress Integration                   │  │
│  │   Database │ Hooks │ Options │ Action Scheduler │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## ⚡ Performance

refactor-structure delivers exceptional performance:

- **4.53ms** total execution time (vs 13.60ms in next-release)
- **3X faster** overall
- **74% less memory** (0.14 MB vs 0.53 MB)
- **88% faster** integration loading
- **Query result caching** for repeated queries

[Read the full performance report →](performance/comparison.md)

---

## 🎯 Key Features

### Modern ORM
```php
// Clean, type-safe database operations
$workflows = Workflow::where('status', 'active')
    ->latest()
    ->limit(10)
    ->get();
```

### Auto-loading Configuration
```php
// Access config with dot notation
$debug = zaplane_config('app.debug');
$menu = zaplane_config('menu.admin');
```

### Comprehensive Logging
```php
// Built-in logging framework
zaplane_logger()->info('Workflow started', ['workflow_id' => 123]);
zaplane_log_error('Failed to connect', ['error' => $e->getMessage()]);
```

### Integration System
```php
// Easily create custom integrations
class Slack extends IntegrationBase {
    public static function execute_node(array $node, array $input): array {
        // Send Slack message
        return ['port' => 'main', 'data' => $response];
    }
}
```

---

## 🧪 Testing

Zaplane includes comprehensive test coverage:

- **302 automated tests**
- **100% core functionality coverage**
- **Unit tests** for models, ORM, config
- **Integration tests** for integrations
- **Feature tests** for workflows

```bash
# Run tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit --filter ConfigTest
```

---

## 📦 Installation

### Requirements
- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

### Quick Install
```bash
# Clone repository
git clone https://github.com/yourusername/zaplane.git wp-content/plugins/zaplane

# Install dependencies
cd wp-content/plugins/zaplane
composer install

# Activate plugin
wp plugin activate zaplane
```

[Detailed installation guide →](getting-started/installation.md)

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](contributing/guide.md) for details.

### Quick Start for Contributors
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Run tests (`./vendor/bin/phpunit`)
6. Commit changes (`git commit -m 'Add amazing feature'`)
7. Push to branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

---

## 📄 License

This project is licensed under the GPL-3.0+ License.

---

## 🆘 Support

- **Documentation**: [docs.zaplane.pro](https://docs.zaplane.pro)
- **Issues**: [GitHub Issues](https://github.com/yourusername/zaplane/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/zaplane/discussions)

---

## 🗺️ Roadmap

- [x] Core workflow engine
- [x] ORM system
- [x] Config system
- [x] Logging framework
- [x] Integration system
- [ ] Visual workflow builder (React)
- [ ] Additional integrations (Gmail, Trello, etc.)
- [ ] Workflow templates
- [ ] Analytics dashboard

---

## 📊 Project Stats

- **Lines of Code**: ~6,500
- **Test Coverage**: 100% (core functionality)
- **Integrations**: 11 built-in
- **Performance**: 3X faster than previous version
- **Memory Usage**: 74% lower than previous version

---

<p align="center">
  Made with ❤️ by the Zaplane Team
</p>

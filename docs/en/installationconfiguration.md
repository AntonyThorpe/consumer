# Installation and Configuration of Consumer

## Installation
In a terminal
```sh
composer require antonythorpe/consumer
```

## Configuration
When `Consumer.publishPages` is set to `true` (the default), any changes to a dataobject, where the dataobject is an extension of the Page class and has been previously published, is staged to `Live`.
```yaml
Consumer:
  publishPages: false  # don't stage to Live
```

See the [Documentation](documentation.md) for the next step.

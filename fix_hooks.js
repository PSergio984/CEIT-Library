const fs = require('fs');
const path = 'C:\\Users\\admin\\.gemini\\settings.json';
const config = JSON.parse(fs.readFileSync(path, 'utf8'));

function fixHook(hook) {
  if (hook.name === 'claude-mem' && hook.type === 'command' && hook.command.startsWith('"C:\\')) {
    if (!hook.command.startsWith('& ')) {
      hook.command = '& ' + hook.command;
      console.log(`Fixed hook: ${hook.command}`);
    }
  }
}

if (config.hooks) {
  Object.values(config.hooks).forEach(eventHooks => {
    eventHooks.forEach(matcherGroup => {
      if (matcherGroup.hooks) {
        matcherGroup.hooks.forEach(fixHook);
      }
    });
  });
}

fs.writeFileSync(path, JSON.stringify(config, null, 2));
console.log('Successfully updated settings.json');

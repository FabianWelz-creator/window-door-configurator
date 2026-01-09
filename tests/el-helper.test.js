const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

class TextNode {
  constructor(text) {
    this.textContent = text;
    this.nodeType = 3;
  }
}

class Element {
  constructor(tagName) {
    this.tagName = tagName.toLowerCase();
    this.attributes = new Map();
    this.children = [];
    this.eventListeners = {};
    this.className = '';
    this.innerHTML = '';
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name === 'value') this.value = String(value);
    if (name === 'type') this.type = String(value);
  }

  appendChild(node) {
    this.children.push(node);
    if (this.tagName === 'select' && node instanceof OptionElement) {
      node.ownerSelect = this;
      this.options.push(node);
      if (node.selected || this.options.length === 1) {
        this.value = node.value ?? node.textContent ?? '';
        this.options.forEach(opt => {
          if (opt !== node && node.selected) opt._selected = false;
        });
        if (node.selected) this._selectedOption = node;
      }
    }
    return node;
  }

  addEventListener(type, handler) {
    this.eventListeners[type] = this.eventListeners[type] || [];
    this.eventListeners[type].push(handler);
  }

  dispatchEvent(event) {
    const handlers = this.eventListeners[event.type] || [];
    handlers.forEach(fn => fn.call(this, event));
  }
}

class OptionElement extends Element {
  constructor() {
    super('option');
    this._selected = false;
  }

  set selected(value) {
    this._selected = !!value;
    if (this.ownerSelect && value) {
      this.ownerSelect.options.forEach(opt => {
        if (opt !== this) opt._selected = false;
      });
      this.ownerSelect._selectedOption = this;
      this.ownerSelect.value = this.value ?? this.textContent ?? '';
    }
  }

  get selected() {
    return !!this._selected;
  }
}

class InputElement extends Element {
  constructor() {
    super('input');
    this.checked = false;
    this.type = '';
  }

  click() {
    if (this.type === 'checkbox') {
      this.checked = !this.checked;
    }
    this.dispatchEvent({ type: 'click', target: this });
  }
}

class SelectElement extends Element {
  constructor() {
    super('select');
    this.options = [];
    this._selectedOption = null;
    this.value = '';
  }

  set value(val) {
    this._value = val;
    if (this.options?.length) {
      this.options.forEach(opt => {
        opt._selected = opt.value === val;
        if (opt._selected) this._selectedOption = opt;
      });
    }
  }

  get value() {
    return this._value;
  }
}

class Document {
  constructor() {
    this.body = new Element('body');
  }

  createElement(tag) {
    if (tag === 'select') return new SelectElement();
    if (tag === 'option') return new OptionElement();
    if (tag === 'input') return new InputElement();
    return new Element(tag);
  }

  createTextNode(text) {
    return new TextNode(text);
  }
}

function getElHelper(document) {
  const configuratorPath = path.join(__dirname, '..', 'public', 'configurator-windows.js');
  const source = fs.readFileSync(configuratorPath, 'utf8');
  const match = source.match(/function el\([^)]*\)\s*\{[\s\S]*?return e;\s*\}/);
  if (!match) throw new Error('el helper not found');
  const script = new vm.Script(match[0]);
  const context = vm.createContext({ document, window: { document } });
  script.runInContext(context);
  return context.el;
}

function runTests() {
  const document = new Document();
  const el = getElHelper(document);

  // Dropdown initial selection
  const select = el('select', {}, [
    el('option', { value: 'a' }, ['A']),
    el('option', { value: 'b', selected: true }, ['B']),
    el('option', { value: 'c' }, ['C'])
  ]);
  assert.strictEqual(select.value, 'b', 'Selected value should be b');
  const selectedOptions = select.options.filter(opt => opt.selected);
  assert.strictEqual(selectedOptions.length, 1, 'Exactly one option should be selected');

  // Dropdown user selection simulation and persistence after re-render
  let capturedValue = null;
  const selectWithHandler = el('select', { onchange: e => { capturedValue = e.target.value; } }, [
    el('option', { value: 'x' }, ['X']),
    el('option', { value: 'y', selected: true }, ['Y']),
    el('option', { value: 'z' }, ['Z'])
  ]);
  selectWithHandler.value = 'z';
  selectWithHandler.dispatchEvent({ type: 'change', target: selectWithHandler });
  assert.strictEqual(capturedValue, 'z', 'Change handler should capture user selection');

  const renderSelect = (value) => el('select', {}, [
    el('option', { value: 'x', selected: value === 'x' }, ['X']),
    el('option', { value: 'y', selected: value === 'y' }, ['Y']),
    el('option', { value: 'z', selected: value === 'z' }, ['Z'])
  ]);
  const rerenderedSelect = renderSelect(capturedValue);
  assert.strictEqual(rerenderedSelect.value, 'z', 'Re-render should keep selected value');

  // Checkbox initial state
  const checkbox = el('input', { type: 'checkbox', checked: true }, []);
  assert.strictEqual(checkbox.checked, true, 'Checkbox should be checked initially');

  // Checkbox toggle persistence
  checkbox.click();
  assert.strictEqual(checkbox.checked, false, 'Checkbox should toggle to unchecked after click');
  const rerenderedCheckbox = el('input', { type: 'checkbox', checked: checkbox.checked }, []);
  assert.strictEqual(rerenderedCheckbox.checked, false, 'Checkbox state should persist after re-render');

  // Attribute inspection
  assert.strictEqual(select.options[0].attributes.get('selected'), undefined, 'Unselected option should not have selected attribute');
  assert.strictEqual(select.options[1].attributes.get('selected'), undefined, 'Selected option should be driven by property, not attribute value string');
  assert.strictEqual(checkbox.attributes.get('checked'), undefined, 'Checked property should not set checked attribute to false');

  // Event handler integrity
  let eventTriggered = false;
  const selectForEvent = el('select', { onchange: () => { eventTriggered = true; } }, [
    el('option', { value: 'one', selected: true }, ['One']),
    el('option', { value: 'two' }, ['Two'])
  ]);
  selectForEvent.dispatchEvent({ type: 'change', target: selectForEvent });
  assert.ok(eventTriggered, 'Event handler should be invoked via addEventListener');
}

runTests();
console.log('All el() helper tests passed.');

import { TextControl, ToggleControl } from '@wordpress/components';
import TextRules     from './RuleInputs/TextRules';
import NameRules     from './RuleInputs/NameRules';
import EmailRules    from './RuleInputs/EmailRules';
import PhoneRules    from './RuleInputs/PhoneRules';
import NumericRules  from './RuleInputs/NumericRules';
import TextareaRules from './RuleInputs/TextareaRules';
import SelectRules   from './RuleInputs/SelectRules';
import CheckboxRules from './RuleInputs/CheckboxRules';
import FileRules     from './RuleInputs/FileRules';
import UrlRules      from './RuleInputs/UrlRules';

const RULES_COMPONENT_MAP = {
    text:     TextRules,
    name:     NameRules,
    email:    EmailRules,
    tel:      PhoneRules,
    number:   NumericRules,
    textarea: TextareaRules,
    url:      UrlRules,
    select:   SelectRules,
    checkbox: CheckboxRules,
    radio:    CheckboxRules,  // Radio reuses CheckboxRules
    file:     FileRules,
};

export default function FieldRuleRow( { field, rules, onChange } ) {
    const { name, type, required: cfRequired } = field;

    const setRule = ( key, val ) => onChange( { ...rules, [ key ]: val } );
    const setRules = ( newRules ) => onChange( newRules );

    const RulesComponent = RULES_COMPONENT_MAP[ type ] || TextRules;

    // Auto-generate label if not set.
    const autoLabel = name
        .replace( /^(your-|the-)/, '' )
        .replace( /[-_]/g, ' ' )
        .replace( /\b\w/g, ( c ) => c.toUpperCase() );

    return (
        <div className="cfv-field-row">
            <div className="cfv-field-row__header">
                <span className="cfv-field-row__name">
                    <code>{ name }</code>
                    <span className="cfv-field-row__type">({ type })</span>
                    { cfRequired && <span className="cfv-field-row__cf-required">CF7 required</span> }
                </span>
            </div>

            <div className="cfv-field-row__body">
                <TextControl
                    label="Field label (used in error messages)"
                    placeholder={ autoLabel }
                    value={ rules.label ?? '' }
                    onChange={ v => setRule( 'label', v ) }
                />
                <ToggleControl
                    label="Required"
                    checked={ !! rules.required }
                    onChange={ v => setRule( 'required', v ) }
                />
                <RulesComponent rules={ rules } onChange={ setRules } />
            </div>
        </div>
    );
}

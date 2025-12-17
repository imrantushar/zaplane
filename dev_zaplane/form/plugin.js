import React from 'react';
import ReactDOM from 'react-dom';
import { Formik, Field, Form } from 'formik';
import Select from 'react-select';
import {active_plugin_lists, makeRequest} from './../helper'
import PropTypes from 'prop-types';

const propTypes = {};

const defaultProps = {};

export default function Plugin(props) {
    return (
        <React.Fragment>
            <h1>Plugin Reset</h1>
            <Formik
            initialValues={{
                plugin: '',
                reset: '',
            }}
            onSubmit={(values) => {
                makeRequest('plugin_reset', {...values, plugin: values?.plugin?.value}).then(() => {
                    alert(JSON.stringify(values, null, 2));
                })
            }}
            >
            {({values, setFieldValue, isSubmitting}) => (
                <Form>
                    <div className='zenreset-field'>
                        <label htmlFor="plugin">Plugin</label>
                        <Select
                            id="plugin"
                            name="plugin"
                            options={active_plugin_lists}
                            value={values.plugin}
                            onChange={(option) => setFieldValue('plugin', option)}
                            placeholder="Select Plugin"
                        />
                    </div>
                    <div className='zenreset-field'>
                        <label htmlFor="reset">Last Name</label>
                        <Field id="reset" name="reset" placeholder="Type reset" />
                    </div>
                    <button type="submit" disabled={isSubmitting}>Reset</button>
                </Form>
            )}
            </Formik>
        </React.Fragment>
    );
}

Plugin.propTypes = propTypes;
Plugin.defaultProps = defaultProps;
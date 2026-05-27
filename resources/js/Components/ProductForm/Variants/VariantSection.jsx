import { useState, useEffect } from 'react';
import AttributeBuilder from './AttributeBuilder';
import VariantTable from './VariantTable';

export default function VariantSection({ variants, setVariants }) {
    const [options, setOptions] = useState([]);

    useEffect(() => {
        if (variants && variants.length > 0 && variants[0].options) {
            const extractedOptions = [];
            const optionCount = variants[0].options.length;
            for (let i = 0; i < optionCount; i++) {
                const uniqueValues = [...new Set(variants.map((v) => v.options?.[i]).filter(Boolean))];
                if (uniqueValues.length > 0) {
                    extractedOptions.push({
                        name: `Option ${i + 1}`,
                        values: uniqueValues,
                    });
                }
            }
            if (extractedOptions.length > 0) {
                setOptions(extractedOptions);
            }
        }
    }, []);

    const handleOptionsChange = (newOptions) => {
        setOptions(newOptions);
    };

    const handleVariantsChange = (newVariants) => {
        setVariants(newVariants);
    };

    return (
        <div className="space-y-5">
            <AttributeBuilder
                options={options}
                setOptions={handleOptionsChange}
            />

            {options.length > 0 && (
                <VariantTable
                    options={options}
                    variants={variants}
                    setVariants={handleVariantsChange}
                />
            )}
        </div>
    );
}

import FormTextarea from '../FormTextarea';

export default function DescriptionSection({ data, setData, errors }) {
    return (
        <FormTextarea
            label="Full Description"
            name="description"
            value={data.description || ''}
            onChange={(e) => setData('description', e.target.value)}
            placeholder="Write a detailed product description..."
            error={errors.description}
            rows={8}
        />
    );
}

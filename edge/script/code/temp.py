import json
input_path = '/data/share/input.json'
output_path = '/data/share/output.json'
# 从文件中读取获得上游参数
with open(input_path, 'r') as f:
    data = json.load(f)

# 自定义中间事件
for key, value in data.items():
    # 这里处理你的逻辑
    print(value)

# 将结果输出到文件中
with open(output_path, 'w') as f:
    data = json.dump(data, f)